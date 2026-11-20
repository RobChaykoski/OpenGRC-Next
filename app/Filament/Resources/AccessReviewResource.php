<?php

namespace App\Filament\Resources;

use App\Enums\AccessReviewStatus;
use App\Filament\Resources\AccessReviewResource\Pages\CreateAccessReview;
use App\Filament\Resources\AccessReviewResource\Pages\ListAccessReviews;
use App\Filament\Resources\AccessReviewResource\Pages\ViewAccessReview;
use App\Filament\Resources\AccessReviewResource\RelationManagers\DecisionsRelationManager;
use App\Models\AccessReview;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Periodic user access review campaigns.
 *
 * The review snapshots accounts and roles at start. The reviewer attests or
 * revokes each account; the campaign is evidence for the access-control control.
 */
class AccessReviewResource extends Resource
{
    protected static ?string $model = AccessReview::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 47;

    protected static ?string $navigationLabel = 'Access reviews';

    protected static ?string $modelLabel = 'Access review';

    protected static ?string $pluralModelLabel = 'Access reviews';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Campaign name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('reviewer_id')
                    ->label('Reviewer')
                    ->relationship('reviewer', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('The person accountable for deciding every account.'),

                DatePicker::make('due_at')
                    ->label('Due'),

                Textarea::make('notes')
                    ->label('Scope and method')
                    ->helperText('What is in scope and how the review is performed.')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('reviewer')
                ->withCount([
                    'decisions',
                    'decisions as pending_count' => fn (Builder $decisions): Builder => $decisions->where('decision', 'pending'),
                ]))
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (AccessReview $record): string => $record->reference()),

                TextColumn::make('name')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('reviewer.name')
                    ->label('Reviewer')
                    ->placeholder('Unassigned'),

                TextColumn::make('decisions_count')
                    ->label('Accounts')
                    ->numeric(),

                TextColumn::make('pending_count')
                    ->label('Pending')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('due_at')
                    ->label('Due')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AccessReviewStatus::class),

                SelectFilter::make('reviewer_id')
                    ->label('Reviewer')
                    ->relationship('reviewer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('review')
                    ->label('Review')
                    ->options([
                        'active' => 'Active',
                        'overdue' => 'Active and overdue',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->where('status', AccessReviewStatus::Active->value),
                            'overdue' => $query->where('status', AccessReviewStatus::Active->value)
                                ->whereNotNull('due_at')
                                ->whereDate('due_at', '<', today()),
                            default => $query,
                        };
                    }),
            ])
            ->emptyStateHeading('No access reviews')
            ->emptyStateDescription('Start a campaign to attest every account and record the decision.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Campaign')
                            ->columnSpanFull(),

                        TextEntry::make('reference')
                            ->label('Reference')
                            ->state(fn (AccessReview $record): string => $record->reference()),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('reviewer.name')
                            ->label('Reviewer')
                            ->placeholder('Unassigned'),

                        TextEntry::make('due_at')
                            ->label('Due')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('started_at')
                            ->label('Started')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('completed_at')
                            ->label('Completed')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Scope and method')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            'decisions' => DecisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessReviews::route('/'),
            'create' => CreateAccessReview::route('/create'),
            'view' => ViewAccessReview::route('/{record}'),
        ];
    }
}
