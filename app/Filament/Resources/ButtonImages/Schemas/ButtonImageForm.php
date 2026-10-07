<?php

namespace App\Filament\Resources\ButtonImages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ButtonImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('diagram')
                    ->url()
                    ->required(),
                Toggle::make('status')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Switched off, this button style disappears from the configurator on every body.'),
            ]);
    }
}
