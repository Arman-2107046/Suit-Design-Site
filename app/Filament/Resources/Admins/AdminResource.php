<?php

namespace App\Filament\Resources\Admins;

use App\Enums\AdminRole;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\Admin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

/* The team. Super admins only — the policy keeps everyone else out. */
class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Team';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Administrators';

    protected static ?string $modelLabel = 'administrator';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Account')
                ->description('How they sign in to the admin. This is a staff account, separate from any customer account with the same email.')
                ->icon(Heroicon::OutlinedUserCircle)
                ->columns(2)
                ->components([
                    TextInput::make('name')->required()->maxLength(255)->autofocus(),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                ]),

            Section::make('Role')
                ->description('What they can do in the admin.')
                ->icon(Heroicon::OutlinedKey)
                ->components([
                    Radio::make('role')
                        ->hiddenLabel()
                        ->options(AdminRole::class)
                        ->default(AdminRole::Admin->value)
                        ->required()
                        /* Nobody changes their own role, so a super admin cannot lock themselves out. */
                        ->disabled(fn (?Admin $record) => $record?->is(Filament::auth()->user()))
                        ->helperText(fn (?Admin $record) => $record?->is(Filament::auth()->user())
                            ? 'This is you. Another super admin can change your role.'
                            : null),
                ]),

            Section::make('Password')
                ->description(fn (string $operation) => $operation === 'edit'
                    ? 'Leave blank to keep the current password.'
                    : 'At least 8 characters. Share it with them privately; they can change it after signing in.')
                ->icon(Heroicon::OutlinedLockClosed)
                ->columns(2)
                ->components([
                    TextInput::make('password')
                        ->label(fn (string $operation) => $operation === 'edit' ? 'New password' : 'Password')
                        ->password()
                        ->revealable()
                        ->rule(Password::min(8))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->confirmed()
                        ->autocomplete('new-password'),
                    TextInput::make('password_confirmation')
                        ->label('Confirm password')
                        ->password()
                        ->revealable()
                        ->required(fn (Get $get) => filled($get('password')))
                        ->dehydrated(false)
                        ->autocomplete('new-password'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'activities as recent_activity_count' => fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(30)),
            ]))
            ->defaultSort('id')
            ->columns([
                ViewColumn::make('name')
                    ->label('Name')
                    ->view('filament.tables.columns.admin-identity')
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->color('gray'),
                TextColumn::make('role')
                    ->badge()
                    ->icon(fn (AdminRole $state) => $state === AdminRole::SuperAdmin ? Heroicon::ShieldCheck : Heroicon::User)
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i')
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('recent_activity_count')
                    ->label('Actions, 30 days')
                    ->numeric()
                    ->alignEnd()
                    ->color(fn (int $state) => $state > 0 ? null : 'gray')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(AdminRole::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('activity')
                        ->label('View activity')
                        ->icon(Heroicon::OutlinedClock)
                        ->url(fn (Admin $record) => ActivityResource::getUrl('index', [
                            'filters' => ['admin_id' => ['value' => $record->getKey()]],
                        ])),
                    DeleteAction::make()
                        ->modalDescription(fn (Admin $record) => "{$record->name} will no longer be able to sign in. Their entries in the activity log stay, under their name."),
                ]),
            ])
            ->emptyStateHeading('No administrators yet');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Admin::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }
}
