<?php

namespace App\Filament\Resources;

use App\Enums\NonHumanIdentityStatus;
use App\Enums\NonHumanIdentityType;
use App\Filament\Resources\NonHumanIdentityResource\Pages\CreateNonHumanIdentity;
use App\Filament\Resources\NonHumanIdentityResource\Pages\EditNonHumanIdentity;
use App\Filament\Resources\NonHumanIdentityResource\Pages\ListNonHumanIdentities;
use App\Filament\Resources\NonHumanIdentityResource\Pages\ViewNonHumanIdentity;
use App\Models\NonHumanIdentity;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Non-human identity register.
 *
 * Service accounts, API tokens, bots, integration credentials and certificates
 * are machine identities, not users. The register records ownership, scope,
 * rotation, expiry and review; the secret stays in the secret store.
 */
class NonHumanIdentityResource extends Resource
{
    protected static ?string $model = NonHumanIdentity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 37;

    protected static ?string $navigationLabel = 'Non-human identities';

    protected static ?string $modelLabel = 'Non-human identity';

    protected static ?string $pluralModelLabel = 'Non-human identities';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label('Type')
                            ->options(NonHumanIdentityType::class)
                            ->required(),

                        TextInput::make('system')
                            ->label('System')
                            ->helperText('The product or service that owns the credential.')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('environment')
                            ->label('Environment')
                            ->placeholder('production, staging, development')
                            ->maxLength(32),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('The person accountable for the identity and its next review.'),

                        Select::make('status')
                            ->label('Status')
                            ->options(NonHumanIdentityStatus::class)
                            ->default(NonHumanIdentityStatus::Active)
                            ->required(),
                    ]),

                Section::make('Purpose, scope and secret')
                    ->columns(2)
                    ->schema([
                        Textarea::make('purpose')
                            ->label('Purpose')
                            ->helperText('Why this machine identity exists. "Integration" is not a purpose.')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('scope')
                            ->label('Scope and permissions')
                            ->helperText('The roles, scopes or resources it can reach.')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('secret_reference')
                            ->label('Secret reference')
                            ->helperText('A secret-store path such as vault://kv/opengrc/wazuh-api. Never the secret itself.')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Rotation and expiry')
                    ->columns(3)
                    ->schema([
                        TextInput::make('rotation_days')
                            ->label('Rotation interval (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->default(90),

                        DatePicker::make('last_rotated_at')
                            ->label('Last rotated'),

                        DatePicker::make('next_rotation_at')
                            ->label('Next rotation')
                            ->helperText('Leave blank to compute from the last rotation and interval.'),

                        DatePicker::make('last_used_at')
                            ->label('Last used'),

                        DatePicker::make('expires_at')
                            ->label('Expires'),

                        DatePicker::make('next_review_at')
                            ->label('Next review'),
                    ]),

                Section::make('Revocation and notes')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('reviewed_at')
                            ->label('Last reviewed'),

                        DatePicker::make('revoked_at')
                            ->label('Revoked'),

                        Textarea::make('revocation_reason')
                            ->label('Revocation reason')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Internal notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Identity')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('system')
                    ->label('System')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('environment')
                    ->label('Env')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('rotation')
                    ->label('Rotation')
                    ->getStateUsing(fn (NonHumanIdentity $record): string => self::rotationLabel($record))
                    ->badge()
                    ->color(fn (NonHumanIdentity $record): string => self::rotationColor($record)),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiry')
                    ->color(fn (NonHumanIdentity $record): string => $record->isExpired() ? 'danger' : 'gray'),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),

                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->date()
                    ->sortable()
                    ->placeholder('Unknown')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(NonHumanIdentityType::class),

                SelectFilter::make('status')
                    ->options(NonHumanIdentityStatus::class),

                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('review')
                    ->label('Review')
                    ->options([
                        'rotation_due' => 'Rotation due',
                        'expired' => 'Expired',
                        'due_soon' => 'Due within 14 days',
                        'no_owner' => 'No owner',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'rotation_due' => $query->whereDate('next_rotation_at', '<', today()),
                            'expired' => $query->whereNotNull('expires_at')->whereDate('expires_at', '<', today()),
                            'due_soon' => $query->where(function (Builder $inner): void {
                                $inner->where(function (Builder $rotation): void {
                                    $rotation->whereNotNull('next_rotation_at')
                                        ->whereDate('next_rotation_at', '>=', today())
                                        ->whereDate('next_rotation_at', '<=', today()->addDays(14));
                                })->orWhere(function (Builder $expiry): void {
                                    $expiry->whereNotNull('expires_at')
                                        ->whereDate('expires_at', '>=', today())
                                        ->whereDate('expires_at', '<=', today()->addDays(14));
                                });
                            }),
                            'no_owner' => $query->whereNull('owner_id'),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No non-human identities')
            ->emptyStateDescription('Register service accounts, tokens and certificates so every machine credential has an owner, a purpose and a rotation date.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->columnSpanFull(),

                        TextEntry::make('type')
                            ->badge(),

                        TextEntry::make('system')
                            ->label('System'),

                        TextEntry::make('environment')
                            ->label('Environment')
                            ->placeholder('—'),

                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unassigned'),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('secret_reference')
                            ->label('Secret reference')
                            ->placeholder('—'),
                    ]),

                Section::make('Purpose and scope')
                    ->schema([
                        TextEntry::make('purpose')
                            ->label('Purpose')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('scope')
                            ->label('Scope and permissions')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Rotation and expiry')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('rotation_days')
                            ->label('Rotation interval')
                            ->suffix(' days')
                            ->placeholder('—'),

                        TextEntry::make('last_rotated_at')
                            ->label('Last rotated')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('next_rotation_at')
                            ->label('Next rotation')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('last_used_at')
                            ->label('Last used')
                            ->date()
                            ->placeholder('Unknown'),

                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->date()
                            ->placeholder('No expiry'),

                        TextEntry::make('next_review_at')
                            ->label('Next review')
                            ->date()
                            ->placeholder('—'),
                    ]),

                Section::make('Revocation and notes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reviewed_at')
                            ->label('Last reviewed')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('revoked_at')
                            ->label('Revoked')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('revocation_reason')
                            ->label('Revocation reason')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Internal notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNonHumanIdentities::route('/'),
            'create' => CreateNonHumanIdentity::route('/create'),
            'view' => ViewNonHumanIdentity::route('/{record}'),
            'edit' => EditNonHumanIdentity::route('/{record}/edit'),
        ];
    }

    public static function rotationLabel(NonHumanIdentity $identity): string
    {
        if ($identity->next_rotation_at === null) {
            return 'No schedule';
        }

        if ($identity->status instanceof NonHumanIdentityStatus && $identity->status->isClosed()) {
            return $identity->next_rotation_at->toDateString();
        }

        $days = $identity->daysUntilRotation();

        return match (true) {
            $days === null => $identity->next_rotation_at->toDateString(),
            $days < 0 => 'Overdue by '.abs($days).' day(s)',
            $days === 0 => 'Due today',
            default => $days.' day(s) left',
        };
    }

    public static function rotationColor(NonHumanIdentity $identity): string
    {
        if ($identity->status instanceof NonHumanIdentityStatus && $identity->status->isClosed()) {
            return 'gray';
        }

        $days = $identity->daysUntilRotation();

        return match (true) {
            $days === null => 'gray',
            $days < 0 => 'danger',
            $days <= 14 => 'warning',
            default => 'success',
        };
    }
}
