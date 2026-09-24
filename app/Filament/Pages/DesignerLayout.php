<?php

namespace App\Filament\Pages;

use App\Models\DesignerSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
class DesignerLayout extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Designer layout';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Designer layout';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(DesignerSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Fabrics')
                    ->description('The swatch grid on the Fabric tab.')
                    ->components([
                        $this->columnsField('fabric_columns', 3)
                            ->helperText('Phones always show two, whatever is chosen here — anything narrower is too small to tap.'),
                    ]),


                Section::make('Style')
                    ->description('Body style, lapels, sleeves and pockets.')
                    ->columns(2)
                    ->components([
                        Select::make('style_layout')
                            ->label('Show options as')
                            ->options(DesignerSetting::LAYOUTS)
                            ->default('grid')
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live()
                            ->required(),

                        $this->columnsField('style_columns', 3)
                            ->visible(fn ($get): bool => $get('style_layout') === 'grid')
                            ->helperText('Only applies to the grid.'),
                    ]),


                Section::make('Custom linings')
                    ->description('The lining swatches behind Accents.')
                    ->components([
                        $this->columnsField('lining_columns', 2),
                    ]),
            ])
            ->statePath('data');
    }

    private function columnsField(string $name, int $default): Select
    {
        return Select::make($name)
            ->label('Columns')
            ->options(array_combine(DesignerSetting::COLUMN_CHOICES, DesignerSetting::COLUMN_CHOICES))
            ->default($default)
            ->selectablePlaceholder(false)
            ->native(false)
            ->required();
    }

    public function save(): void
    {
        DesignerSetting::current()->update($this->form->getState());

        Notification::make()
            ->title('Designer layout saved')
            ->body('Reload the designer to see it.')
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
                        Action::make('save')->label('Save changes')->submit('save')->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }
}
