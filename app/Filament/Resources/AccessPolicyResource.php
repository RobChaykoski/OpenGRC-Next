<?php

namespace App\Filament\Resources;

use App\Enums\AccessAction;
use App\Enums\AccessorProfile;
use App\Enums\ContextTrust;
use App\Enums\DocumentClassification;
use App\Filament\Resources\AccessPolicyResource\Pages\CreateAccessPolicy;
use App\Filament\Resources\AccessPolicyResource\Pages\EditAccessPolicy;
use App\Filament\Resources\AccessPolicyResource\Pages\ListAccessPolicies;
use App\Filament\Resources\AccessPolicyResource\Pages\ViewAccessPolicy;
use App\Models\AccessPolicy;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Access policies (ADR 0049).
 *
 * classification x profile x context -> action. A policy is data, not code: the
 * reviewer can see the rule that fired, and the version and hash recorded on
 * each decision stay reproducible after an edit.
 */
class AccessPolicyResource extends Resource
{
    protected static ?string $model = AccessPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Access governance';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Access policies';

    protected static ?string $modelLabel = 'Access policy';

    protected static ?string $pluralModelLabel = 'Access policies';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Rule')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(32)
                            ->unique(ignoreRecord: true)
                            ->helperText('Stable identifier used in reports and decision records.'),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('action')
                            ->options(AccessAction::class)
                            ->required()
                            ->helperText('Deny always wins over an allow at any priority.'),

                        TextInput::make('priority')
                            ->numeric()
                            ->default(100)
                            ->required()
                            ->helperText('Lower number wins. Specificity breaks a tie inside the same priority.'),

                        TextInput::make('max_duration_minutes')
                            ->numeric()
                            ->helperText('For a time-boxed action. Capped by the configured maximum.'),

                        Select::make('tiers')
                            ->label('Applies to levels')
                            ->multiple()
                            ->options(DocumentClassification::class)
                            ->required()
                            ->helperText('At least one level. An unlisted level falls through to default deny.'),

                        Select::make('profiles')
                            ->label('Applies to profiles')
                            ->multiple()
                            ->options(AccessorProfile::class)
                            ->helperText('Leave empty for every profile.'),

                        TagsInput::make('mask_columns')
                            ->label('Columns to mask')
                            ->helperText('Column names only. Do not paste column values.'),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('What decision this rule makes, in one or two sentences.'),
                    ]),

                Section::make('Conditions')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('conditions.min_trust')
                            ->label('Minimum context trust')
                            ->options(ContextTrust::class)
                            ->placeholder('Any trust level'),

                        Toggle::make('conditions.requires_managed_device')
                            ->label('Managed device required'),

                        Toggle::make('requires_justification')
                            ->label('Justification required')
                            ->helperText('The requester must submit a reason before the rule applies.'),

                        Toggle::make('requires_review')
                            ->label('Privacy review required')
                            ->helperText('Creates a review obligation with a due date on every grant.'),

                        Toggle::make('dry_run')
                            ->label('Dry run')
                            ->helperText('Records the decision but returns an audited allow. Use before blocking.'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Section::make('Governance')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('effective_from')
                            ->label('Effective from'),

                        DatePicker::make('review_due_at')
                            ->label('Review due')
                            ->helperText('An overdue policy is reported, not silently trusted.'),

                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Rationale and known exceptions. No personal data.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner'))
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('action')
                    ->badge()
                    ->sortable(),

                TextColumn::make('tiers')
                    ->label('Levels')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->toggleable(),

                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('version')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('dry_run')
                    ->label('Dry run')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Dry run' : 'Enforcing')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('review_due_at')
                    ->label('Review due')
                    ->date()
                    ->sortable()
                    ->color(fn (AccessPolicy $record): string => $record->isReviewOverdue() ? 'danger' : 'gray'),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unowned')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(AccessAction::class),

                SelectFilter::make('is_active')
                    ->label('State')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),

                SelectFilter::make('review')
                    ->options([
                        'overdue' => 'Review overdue',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => ($data['value'] ?? null) === 'overdue'
                        ? $query->whereNotNull('review_due_at')->whereDate('review_due_at', '<', today())
                        : $query),
            ])
            ->emptyStateHeading('No access policies')
            ->emptyStateDescription('Without a policy every request is denied. Start with a monitor-only rule.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->fontFamily('mono'),

                        TextEntry::make('name')
                            ->columnSpan(2),

                        TextEntry::make('action')
                            ->badge(),

                        TextEntry::make('priority')
                            ->numeric(),

                        TextEntry::make('version')
                            ->numeric(),

                        TextEntry::make('tiers')
                            ->label('Levels')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state),

                        TextEntry::make('profiles')
                            ->label('Profiles')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                            ->placeholder('Every profile'),

                        TextEntry::make('max_duration_minutes')
                            ->label('Maximum duration')
                            ->suffix(' minutes')
                            ->placeholder('—'),
                    ]),

                Section::make('Conditions')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('conditions.min_trust')
                            ->label('Minimum trust')
                            ->placeholder('Any'),

                        TextEntry::make('requires_justification')
                            ->label('Justification')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Required' : 'Not required'),

                        TextEntry::make('requires_review')
                            ->label('Privacy review')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Required' : 'Not required'),

                        TextEntry::make('dry_run')
                            ->label('Mode')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Dry run' : 'Enforcing'),

                        TextEntry::make('rules_hash')
                            ->label('Rule hash')
                            ->fontFamily('mono')
                            ->copyable()
                            ->placeholder('—')
                            ->columnSpan(2),

                        TextEntry::make('mask_columns')
                            ->label('Masked columns')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                            ->placeholder('—')
                            ->columnSpan(3),
                    ]),

                Section::make('Governance')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unowned'),

                        TextEntry::make('effective_from')
                            ->label('Effective from')
                            ->date()
                            ->placeholder('Immediately'),

                        TextEntry::make('review_due_at')
                            ->label('Review due')
                            ->date()
                            ->placeholder('Not set')
                            ->color(fn (AccessPolicy $record): string => $record->isReviewOverdue() ? 'danger' : 'gray'),

                        TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessPolicies::route('/'),
            'create' => CreateAccessPolicy::route('/create'),
            'view' => ViewAccessPolicy::route('/{record}'),
            'edit' => EditAccessPolicy::route('/{record}/edit'),
        ];
    }
}
