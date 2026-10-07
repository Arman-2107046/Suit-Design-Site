<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\SuperAdminOnly;
use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\RankYakSetting;
use App\Services\RankYak\RankYakClient;
use App\Services\RankYak\RankYakImporter;
use App\Services\RankYak\RankYakSync;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * RankYak writes SEO articles; this is where the admin connects it to the
 * journal. Site-wide, so super admins only.
 *
 * @property-read Schema $form
 */
class RankYak extends Page
{
    use SuperAdminOnly;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'RankYak';

    protected static string|\UnitEnum|null $navigationGroup = 'Journal';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'RankYak';

    protected static ?string $slug = 'rankyak';

    protected ?string $subheading = 'Publish the SEO articles RankYak writes straight into the journal, with their titles, meta descriptions and header images.';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = RankYakSetting::current();

        $this->form->fill([
            ...$settings->only(['enabled', 'publish_mode', 'blog_category_id', 'admin_id', 'copy_images', 'report_urls']),
            'admin_id' => $settings->admin_id ?? Admin::query()->superAdmins()->orderBy('id')->value('id'),
            'api_key' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connection')
                    ->description('Switch the integration on, then add your API key so the journal can sync and tell RankYak where each article went live.')
                    ->icon(Heroicon::OutlinedLink)
                    ->columns(2)
                    ->components([
                        Toggle::make('enabled')
                            ->label('Accept articles from RankYak')
                            ->helperText('While this is off, the webhook answers “switched off” and nothing is imported.')
                            ->columnSpanFull(),
                        TextInput::make('api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->placeholder(fn () => RankYakSetting::current()->hasApiKey() ? 'Saved. Leave blank to keep it' : 'Paste the key from RankYak')
                            ->helperText('RankYak → Settings → Integrations → API. Optional, but needed for Sync now, the hourly catch-up and reporting live URLs. Stored encrypted.')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Publishing')
                    ->description('How a new article lands in the journal. You can still edit any post afterwards.')
                    ->icon(Heroicon::OutlinedNewspaper)
                    ->columns(2)
                    ->components([
                        Radio::make('publish_mode')
                            ->label('When an article arrives')
                            ->options([
                                RankYakSetting::PUBLISH => 'Publish it on RankYak’s schedule',
                                RankYakSetting::DRAFT => 'Save it as a draft for me to review',
                            ])
                            ->descriptions([
                                RankYakSetting::PUBLISH => 'Goes live at the time RankYak planned. A future date waits until then.',
                                RankYakSetting::DRAFT => 'Nothing goes live until you publish it from Posts.',
                            ])
                            ->required()
                            ->columnSpanFull(),
                        Select::make('blog_category_id')
                            ->label('Category')
                            ->options(fn () => BlogCategory::query()->orderBy('sort_order')->pluck('name', 'id'))
                            ->placeholder('No category')
                            ->native(false),
                        Select::make('admin_id')
                            ->label('Author')
                            ->options(fn () => Admin::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('No author')
                            ->native(false),
                        Toggle::make('copy_images')
                            ->label('Copy header images to Cloudflare Images')
                            ->helperText('Served and resized like every other picture on the site, and kept even if RankYak removes its copy.'),
                        Toggle::make('report_urls')
                            ->label('Report live URLs back to RankYak')
                            ->helperText('RankYak uses them to verify articles for its backlink exchange.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.rankyak.connection')->viewData(fn () => $this->connectionData()),

            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save changes')->submit('save')->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),

            View::make('filament.pages.rankyak.imports')->viewData(fn () => [
                'posts' => BlogPost::query()->where('source', RankYakImporter::SOURCE)->with('category')->latest('id')->limit(10)->get(),
                'total' => BlogPost::query()->where('source', RankYakImporter::SOURCE)->count(),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = RankYakSetting::current();

        $settings->fill(collect($data)->except(blank($data['api_key'] ?? null) ? ['api_key'] : [])->all())->save();

        $this->form->fill([...$data, 'api_key' => null]);

        Notification::make()->title('RankYak settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->disabled(fn () => ! RankYakSetting::current()->hasApiKey())
                ->tooltip(fn () => RankYakSetting::current()->hasApiKey() ? null : 'Save an API key first')
                ->action(function () {
                    try {
                        (new RankYakClient(RankYakSetting::current()->api_key))->check();
                        Notification::make()->title('Connected to RankYak')->body('The API key works.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Could not connect')->body(RankYakClient::explain($e))->danger()->persistent()->send();
                    }
                }),

            Action::make('sync')
                ->label('Sync now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->disabled(fn () => ! RankYakSetting::current()->enabled || ! RankYakSetting::current()->hasApiKey())
                ->tooltip(fn () => RankYakSetting::current()->enabled && RankYakSetting::current()->hasApiKey() ? 'Import anything new and report live posts' : 'Switch the integration on and save an API key first')
                ->action(function (RankYakSync $sync) {
                    $tally = $sync->run();

                    $body = sprintf('%d new, %d refreshed, %d unchanged. %d live %s reported to RankYak.',
                        $tally['imported'], $tally['refreshed'], $tally['unchanged'], $tally['reported'], str('post')->plural($tally['reported']));

                    $tally['failed'] === []
                        ? Notification::make()->title('Synced with RankYak')->body($body)->success()->send()
                        : Notification::make()->title('Synced, with problems')->body($body.' '.implode(' ', array_slice($tally['failed'], 0, 3)))->warning()->persistent()->send();
                }),

            Action::make('regenerate')
                ->label('New webhook URL')
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Replace the webhook URL?')
                ->modalDescription('The current URL stops working at once. Paste the new one into RankYak afterwards, or new articles will not arrive.')
                ->modalSubmitActionLabel('Replace it')
                ->action(function () {
                    RankYakSetting::current()->forceFill(['webhook_token' => RankYakSetting::newToken()])->save();
                    Notification::make()->title('New webhook URL ready')->body('Copy it into RankYak → Settings → Integrations → Webhook.')->success()->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    private function connectionData(): array
    {
        $settings = RankYakSetting::current();

        return [
            'settings' => $settings,
            'webhookUrl' => $settings->webhookUrl(),
            'isPublic' => ! preg_match('#^https?://(localhost|127\.|[^/]+\.test(?:[:/]|$))#i', $settings->webhookUrl()),
            'imported' => BlogPost::query()->where('source', RankYakImporter::SOURCE)->count(),
        ];
    }
}
