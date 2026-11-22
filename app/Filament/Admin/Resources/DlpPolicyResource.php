<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DlpChannel;
use App\Enums\DlpPolicyAction;
use App\Enums\DocumentClassification;
use App\Filament\Admin\Resources\DlpPolicyResource\Pages\CreateDlpPolicy;
use App\Filament\Admin\Resources\DlpPolicyResource\Pages\EditDlpPolicy;
use App\Filament\Admin\Resources\DlpPolicyResource\Pages\ListDlpPolicies;
use App\Models\DlpPolicy;
use App\Services\Dlp\DlpIdentifierCatalog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * DLP policies as governance records (ADR 0053).
 *
 * The policy states intent: channels, data classes, identifier types, action,
 * owner and review date. The scanning engine enforces it; OpenGRC records what
 * was approved and when it is due for review.
 */
class DlpPolicyResource extends Resource
{
    protected static ?string $model = DlpPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 44;

    protected static ?string $navigationLabel = 'DLP policies';

    protected static ?string $modelLabel = 'DLP policy';

    protected static ?string $pluralModelLabel = 'DLP policies';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Policy')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(32)
                            ->unique(ignoreRecord: true)
                            ->helperText('Stable identifier used in reports and finding records.'),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('action')
                            ->options(DlpPolicyAction::class)
                            ->required()
                            ->helperText('What the enforcement technology does on a match. Monitor still requires a review.'),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('review_due_at')
                            ->label('Review due')
                            ->helperText('An overdue policy is reported, not silently trusted.'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('What the policy protects and why, in one or two sentences.'),
                    ]),

                Section::make('Coverage')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('channels')
                            ->label('Channels')
                            ->multiple()
                            ->options(DlpChannel::class)
                            ->helperText('Where the policy applies. Leave empty to record a policy that is not scoped yet.'),

                        Select::make('classifications')
                            ->label('Data classes')
                            ->multiple()
                            ->options(DocumentClassification::class),

                        Select::make('identifier_types')
                            ->label('Identifier types')
                            ->multiple()
                            ->options(fn (): array => app(DlpIdentifierCatalog::class)->options())
                            ->helperText('Measured identifier codes. Empty means every type the policy sees.'),
                    ]),

                Section::make('Notes')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->helperText('Review notes, exceptions agreed with the business, or the detector that enforces this.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner')->withCount('findings'))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Reference')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('action')
                    ->badge(),

                TextColumn::make('channels')
                    ->label('Channels')
                    ->formatStateUsing(fn ($state): string => collect((array) $state)
                        ->map(fn (string $channel): string => DlpChannel::tryFrom($channel)?->getLabel() ?? $channel)
                        ->implode(', '))
                    ->placeholder('Not scoped')
                    ->toggleable(),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->searchable(),

                TextColumn::make('findings_count')
                    ->label('Findings')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('review_due_at')
                    ->label('Review due')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (DlpPolicy $record): string => $record->isReviewOverdue() ? 'danger' : 'gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(DlpPolicyAction::class),

                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options(['1' => 'Active', '0' => 'Inactive']),
            ])
            ->emptyStateHeading('No DLP policies')
            ->emptyStateDescription('Record the data classes, channels and action your DLP programme has approved.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDlpPolicies::route('/'),
            'create' => CreateDlpPolicy::route('/create'),
            'edit' => EditDlpPolicy::route('/{record}/edit'),
        ];
    }
}
