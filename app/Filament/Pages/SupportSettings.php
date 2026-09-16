<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
class SupportSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Support settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Support settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact us')
                    ->description('Shown on /contact next to the form.')
                    ->columns(2)
                    ->components([
                        TextInput::make('contact_email')->label('Email')->email()->placeholder('hello@customtailor.com'),
                        TextInput::make('contact_phone')->label('Phone')->placeholder('+880 1700 000000'),
                        Textarea::make('contact_address')->label('Address')->rows(3)->placeholder("12 Gulshan Avenue\nDhaka 1212, Bangladesh"),
                        TextInput::make('contact_hours')->label('Hours')->placeholder('Saturday – Thursday, 10:00 – 20:00'),
                        Textarea::make('contact_intro')->label('Intro')->rows(2)->placeholder('A question about cloth, fit or an order? We answer within one working day.')->columnSpanFull(),
                        $this->imageField('contact', 'Photo')->helperText('Portrait or square. Shown beside the contact details.'),
                        TextInput::make('notify_email')->label('Notify this address')->email()->helperText('Where new messages and sample requests are emailed. Falls back to the contact email.'),
                    ]),

                Section::make('Fabric samples')
                    ->description('Shown on /samples.')
                    ->columns(2)
                    ->components([
                        Textarea::make('samples_intro')->label('Intro')->rows(3)->placeholder('Hold the cloth to the light before you decide. Pick up to five and we post them free.')->columnSpanFull(),
                        TextInput::make('samples_max')->label('Maximum per request')->numeric()->minValue(1)->maxValue(20)->default(5),
                        $this->imageField('samples', 'Photo'),
                    ]),

                Section::make('Track order')
                    ->description('Shown on /track.')
                    ->components([
                        $this->imageField('track', 'Photo'),
                    ]),
            ])
            ->statePath('data');
    }

    private function imageField(string $name, string $label): FileUpload
    {
        return FileUpload::make("{$name}_image")
            ->label($label)
            ->disk('cloudinary')
            ->directory('support')
            ->image()
            ->imageEditor()
            ->maxSize(10240)
            ->fetchFileInformation(false);
    }

    public function save(): void
    {
        SiteSetting::current()->update($this->form->getState());

        Notification::make()->title('Support settings saved')->success()->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save changes')->submit('save')->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }
}
