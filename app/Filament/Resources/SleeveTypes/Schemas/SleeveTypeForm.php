<?php

namespace App\Filament\Resources\SleeveTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SleeveTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('diagram')
                    ->url()
                    ->required(),
                Toggle::make('is_default')
                    ->label('Default shoulder')
                    ->helperText('Every fabric starts on this shoulder. Turning it on here turns it off on the others.'),
            ]);
    }
}
