<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\StreamVideoUpload;
use App\Services\Cloudflare\CloudflareStream;
use App\Models\HomepageSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read Schema $form
 */
class Homepage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Homepage';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Homepage';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = HomepageSetting::current();
        $data = $settings->attributesToArray();

        foreach (HomepageSetting::LOGO_LISTS as $list) {
            $data[$list] = array_column($settings->{$list} ?? [], 'path');
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero')
                    ->description('The full-width photo behind "Dress the real you". Landscape, at least 2400px wide.')
                    ->components([
                        $this->imageField('hero', 'Hero image'),
                    ]),

                Section::make('Designer showcase')
                    ->description('The device frame next to "High-tech tailoring for every body". A video plays muted on loop; the image is used as its poster, or on its own when there is no video.')
                    ->columns(2)
                    ->components([
                        StreamVideoUpload::make('designer_video_url')
                            ->label('Video')
                            ->maxMegabytes(200)
                            ->helperText('Landscape, 16:10 or 16:9. Keep it short — it loops.'),
                        $this->imageField('designer', 'Image / poster')->helperText('Shown while the video loads, or instead of it. 16:10.'),
                    ]),

                Section::make('Section photos')
                    ->description('Each slot shows a neutral panel until a photo is uploaded.')
                    ->columns(2)
                    ->components([
                        $this->imageField('planet', 'Left of "Our planet appreciates it"')->helperText('Tall or square works best.'),
                        $this->imageField('tailor', 'Right of "Looks that last"')->helperText('Tall or square works best.'),
                    ]),

                Section::make('Footer logos')
                    ->description('Shown at the bottom of the homepage. Drag to reorder. Transparent PNG or SVG, roughly 3:2, looks best.')
                    ->columns(2)
                    ->components([
                        $this->logoField('payment_logos', 'Payment methods'),
                        $this->logoField('shipping_logos', 'Shipping partners'),
                    ]),

                Section::make('Social links')
                    ->description('Shown in the homepage footer. Leave a field empty to hide that icon.')
                    ->columns(2)
                    ->components([
                        TextInput::make('instagram_url')->label('Instagram')->url()->placeholder('https://instagram.com/…'),
                        TextInput::make('facebook_url')->label('Facebook')->url()->placeholder('https://facebook.com/…'),
                        TextInput::make('x_url')->label('X')->url()->placeholder('https://x.com/…'),
                        TextInput::make('pinterest_url')->label('Pinterest')->url()->placeholder('https://pinterest.com/…'),
                        TextInput::make('tiktok_url')->label('TikTok')->url()->placeholder('https://tiktok.com/@…'),
                    ]),
            ])
            ->statePath('data');
    }

    private function imageField(string $name, string $label): FileUpload
    {
        return FileUpload::make("{$name}_image")
            ->label($label)
            ->disk('cloudflare')
            ->directory('homepage')
            ->image()
            ->imageEditor()
            ->maxSize(10240)
            ->fetchFileInformation(false);
    }

    private function logoField(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk('cloudflare')
            ->directory('homepage/logos')
            ->image()
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->panelLayout('grid')
            ->imagePreviewHeight('64')
            ->maxSize(2048)
            ->fetchFileInformation(false);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = HomepageSetting::current();
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('cloudflare');

        // Resolve public URLs once here so the storefront never has to ask Cloudflare.
        foreach (HomepageSetting::IMAGES as $image) {
            $path = $data["{$image}_image"] ?? null;
            $previous = $settings->{"{$image}_image"};

            if ($path === $previous) {
                continue;
            }

            $data["{$image}_image_url"] = filled($path) ? $disk->url($path) : null;

            if (filled($previous)) {
                rescue(fn () => $disk->delete($previous), report: false);
            }
        }

        // Logo lists: keep known URLs, resolve new paths, delete whatever was removed.
        foreach (HomepageSetting::LOGO_LISTS as $list) {
            $known = collect($settings->{$list} ?? [])->keyBy('path');
            $paths = array_values(array_filter($data[$list] ?? []));

            $data[$list] = array_map(
                fn (string $path) => ['path' => $path, 'url' => $known[$path]['url'] ?? $disk->url($path)],
                $paths
            );

            foreach ($known->keys()->diff($paths) as $removed) {
                rescue(fn () => $disk->delete($removed), report: false);
            }
        }

        // The video is uploaded from the browser to Stream, so only the old one needs cleaning up here.
        $video = $data['designer_video_url'] ?? null;
        if ($video !== $settings->designer_video_url) {
            $previous = CloudflareStream::uidFromUrl($settings->designer_video_url);
            if ($previous) {
                rescue(fn () => app(CloudflareStream::class)->delete($previous), report: false);
            }
        }

        $settings->update($data);

        Notification::make()
            ->title('Homepage saved')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Save changes')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }
}
