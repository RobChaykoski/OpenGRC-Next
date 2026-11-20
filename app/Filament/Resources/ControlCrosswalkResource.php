<?php

namespace App\Filament\Resources;

use App\Enums\CrosswalkConfidence;
use App\Enums\CrosswalkStatus;
use App\Filament\Resources\ControlCrosswalkResource\Pages\CreateControlCrosswalk;
use App\Filament\Resources\ControlCrosswalkResource\Pages\EditControlCrosswalk;
use App\Filament\Resources\ControlCrosswalkResource\Pages\ListControlCrosswalks;
use App\Filament\Resources\ControlCrosswalkResource\Pages\ViewControlCrosswalk;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use App\Models\Standard;
use App\Models\User;
use App\Services\Controls\ControlCrosswalkService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reviewed mappings between two imported controls.
 *
 * A crosswalk records programme intent, not textual equivalence. Only an admin
 * can approve or reject; editors can draft. A mapping is never deleted when a
 * standard is revised; it is reviewed and replaced.
 */
class ControlCrosswalkResource extends Resource
{
    protected static ?string $model = ControlCrosswalk::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 43;

    public static function getNavigationLabel(): string
    {
        return 'Control crosswalks';
    }

    public static function getModelLabel(): string
    {
        return 'Control crosswalk';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Control crosswalks';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Mapping')
                    ->columns(2)
                    ->schema([
                        Select::make('source_control_id')
                            ->label('Source control')
                            ->relationship('sourceControl', 'code')
                            ->getOptionLabelFromRecordUsing(fn (Control $record): string => "({$record->standard?->code}) {$record->code} — {$record->title}")
                            ->searchable(['code', 'title'])
                            ->required()
                            ->disabled(fn (?ControlCrosswalk $record): bool => $record !== null && $record->status !== CrosswalkStatus::Draft),

                        Select::make('target_control_id')
                            ->label('Target control')
                            ->relationship('targetControl', 'code')
                            ->getOptionLabelFromRecordUsing(fn (Control $record): string => "({$record->standard?->code}) {$record->code} — {$record->title}")
                            ->searchable(['code', 'title'])
                            ->required()
                            ->different('source_control_id')
                            ->disabled(fn (?ControlCrosswalk $record): bool => $record !== null && $record->status !== CrosswalkStatus::Draft),

                        Textarea::make('rationale')
                            ->label('Rationale')
                            ->helperText('Why do these two requirements serve the same programme intent? Point at the text, not the code prefix.')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull()
                            ->disabled(fn (?ControlCrosswalk $record): bool => $record !== null && $record->status !== CrosswalkStatus::Draft),

                        Select::make('confidence')
                            ->label('Confidence')
                            ->options(CrosswalkConfidence::class)
                            ->default(CrosswalkConfidence::Medium)
                            ->required()
                            ->disabled(fn (?ControlCrosswalk $record): bool => $record !== null && $record->status !== CrosswalkStatus::Draft),

                        Hidden::make('created_by')
                            ->default(fn (): ?int => auth()->id()),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Crosswalk')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Reference')
                            ->state(fn (ControlCrosswalk $record): string => $record->reference()),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?CrosswalkStatus $state): ?string => $state?->getLabel())
                            ->color(fn (?CrosswalkStatus $state): string|array|null => $state?->getColor()),

                        TextEntry::make('confidence')
                            ->label('Confidence')
                            ->badge()
                            ->formatStateUsing(fn (?CrosswalkConfidence $state): ?string => $state?->getLabel())
                            ->color(fn (?CrosswalkConfidence $state): string|array|null => $state?->getColor()),

                        TextEntry::make('source')
                            ->label('Source control')
                            ->state(fn (ControlCrosswalk $record): string => $record->sourceControl
                                ? "({$record->sourceControl->standard?->code}) {$record->sourceControl->code} — {$record->sourceControl->title}"
                                : '—'),

                        TextEntry::make('target')
                            ->label('Target control')
                            ->state(fn (ControlCrosswalk $record): string => $record->targetControl
                                ? "({$record->targetControl->standard?->code}) {$record->targetControl->code} — {$record->targetControl->title}"
                                : '—'),

                        TextEntry::make('rationale')
                            ->label('Rationale')
                            ->columnSpanFull(),

                        TextEntry::make('review_note')
                            ->label('Review note')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('creator.name')
                            ->label('Created by')
                            ->placeholder('—'),

                        TextEntry::make('reviewer.name')
                            ->label('Reviewed by')
                            ->placeholder('—'),

                        TextEntry::make('reviewed_at')
                            ->label('Reviewed')
                            ->dateTime('M j, Y H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'sourceControl.standard',
                'targetControl.standard',
                'creator',
                'reviewer',
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (ControlCrosswalk $record): string => $record->reference())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('sourceControl.code')
                    ->label('Source')
                    ->description(fn (ControlCrosswalk $record): ?string => $record->sourceControl?->standard?->code)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('targetControl.code')
                    ->label('Target')
                    ->description(fn (ControlCrosswalk $record): ?string => $record->targetControl?->standard?->code)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('confidence')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(fn (?CrosswalkConfidence $state): ?string => $state?->getLabel())
                    ->color(fn (?CrosswalkConfidence $state): string|array|null => $state?->getColor()),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?CrosswalkStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?CrosswalkStatus $state): string|array|null => $state?->getColor()),

                TextColumn::make('rationale')
                    ->label('Rationale')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('reviewer.name')
                    ->label('Reviewed by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime('M j, Y')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CrosswalkStatus::class),

                SelectFilter::make('confidence')
                    ->options(CrosswalkConfidence::class),

                SelectFilter::make('source_standard_id')
                    ->label('Source framework')
                    ->options(fn (): array => Standard::query()->orderBy('code')->pluck('code', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => is_numeric($data['value'] ?? null)
                        ? $query->whereHas('sourceControl', fn (Builder $control): Builder => $control->where('standard_id', (int) $data['value']))
                        : $query),

                SelectFilter::make('target_standard_id')
                    ->label('Target framework')
                    ->options(fn (): array => Standard::query()->orderBy('code')->pluck('code', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => is_numeric($data['value'] ?? null)
                        ? $query->whereHas('targetControl', fn (Builder $control): Builder => $control->where('standard_id', (int) $data['value']))
                        : $query),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (ControlCrosswalk $record): bool => $record->status === CrosswalkStatus::Draft),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ControlCrosswalk $record): bool => $record->status === CrosswalkStatus::Draft
                        && (auth()->user()?->isAdmin() ?? false))
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('review_note')
                            ->label('Review note')
                            ->rows(2),
                    ])
                    ->action(function (ControlCrosswalk $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(ControlCrosswalkService::class)->approve($record, $user, $data['review_note'] ?? null);

                        Notification::make()->title('Crosswalk approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ControlCrosswalk $record): bool => $record->status === CrosswalkStatus::Draft
                        && (auth()->user()?->isAdmin() ?? false))
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('review_note')
                            ->label('Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (ControlCrosswalk $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(ControlCrosswalkService::class)->reject($record, $user, (string) $data['review_note']);

                        Notification::make()->title('Crosswalk rejected')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('No control crosswalks')
            ->emptyStateDescription('Map two controls from different frameworks when a canonical outcome is too coarse.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListControlCrosswalks::route('/'),
            'create' => CreateControlCrosswalk::route('/create'),
            'view' => ViewControlCrosswalk::route('/{record}'),
            'edit' => EditControlCrosswalk::route('/{record}/edit'),
        ];
    }
}
