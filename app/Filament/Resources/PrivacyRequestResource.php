<?php

namespace App\Filament\Resources;

use App\Enums\PrivacyRegime;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Filament\Resources\PrivacyRequestResource\Pages\CreatePrivacyRequest;
use App\Filament\Resources\PrivacyRequestResource\Pages\EditPrivacyRequest;
use App\Filament\Resources\PrivacyRequestResource\Pages\ListPrivacyRequests;
use App\Filament\Resources\PrivacyRequestResource\Pages\ViewPrivacyRequest;
use App\Models\PrivacyRequest;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
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
 * Privacy request register (DSARs and related rights).
 *
 * This is deliberately separate from the audit-oriented DataRequest: the regime
 * sets a statutory clock, the request type sets the rights vocabulary, and the
 * verification and denial decisions have their own record.
 */
class PrivacyRequestResource extends Resource
{
    protected static ?string $model = PrivacyRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 36;

    protected static ?string $navigationLabel = 'Privacy requests';

    protected static ?string $modelLabel = 'Privacy request';

    protected static ?string $pluralModelLabel = 'Privacy requests';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Request')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reference')
                            ->label('Reference')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Generated on save'),

                        Select::make('status')
                            ->label('Status')
                            ->options(PrivacyRequestStatus::class)
                            ->default(PrivacyRequestStatus::Received)
                            ->required(),

                        Select::make('regime')
                            ->label('Regime')
                            ->options(PrivacyRegime::class)
                            ->required()
                            ->helperText(function (mixed $state): string {
                                $regime = $state instanceof PrivacyRegime
                                    ? $state
                                    : (is_string($state) ? PrivacyRegime::tryFrom($state) : null);

                                return $regime?->citation() ?? '';
                            }),

                        Select::make('request_type')
                            ->label('Request type')
                            ->options(PrivacyRequestType::class)
                            ->required(),

                        DatePicker::make('received_at')
                            ->label('Received')
                            ->default(today())
                            ->required(),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Requester and verification')
                    ->columns(2)
                    ->schema([
                        TextInput::make('requester_name')
                            ->label('Requester name')
                            ->maxLength(255)
                            ->helperText('Use only what is needed to identify the request.'),

                        TextInput::make('contact_reference')
                            ->label('Contact reference')
                            ->maxLength(255)
                            ->helperText('A case reference is preferable to storing full contact details here.'),

                        Toggle::make('identity_verified')
                            ->label('Identity verified'),

                        TextInput::make('verification_method')
                            ->label('Verification method')
                            ->maxLength(255),

                        DatePicker::make('verified_at')
                            ->label('Verified on'),
                    ]),

                Section::make('Deadlines')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('due_at')
                            ->label('Due')
                            ->helperText('Leave blank to compute from the regime and received date. Clear it to recompute.'),

                        DatePicker::make('extended_at')
                            ->label('Extended due date'),

                        Textarea::make('extension_reason')
                            ->label('Extension reason')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Resolution')
                    ->columns(2)
                    ->schema([
                        Textarea::make('response_summary')
                            ->label('Response summary')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('denial_reason')
                            ->label('Denial reason')
                            ->rows(2)
                            ->columnSpanFull(),

                        DatePicker::make('completed_at')
                            ->label('Completed'),

                        TextInput::make('evidence_reference')
                            ->label('Evidence reference')
                            ->maxLength(255),

                        TextInput::make('evidence_sha256')
                            ->label('Evidence SHA-256')
                            ->maxLength(64),

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
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('regime')
                    ->badge()
                    ->sortable(),

                TextColumn::make('request_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('received_at')
                    ->label('Received')
                    ->date()
                    ->sortable(),

                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->getStateUsing(fn (PrivacyRequest $record): string => self::deadlineLabel($record))
                    ->badge()
                    ->color(fn (PrivacyRequest $record): string => self::deadlineColor($record)),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('regime')
                    ->options(PrivacyRegime::class),

                SelectFilter::make('request_type')
                    ->label('Type')
                    ->options(PrivacyRequestType::class),

                SelectFilter::make('status')
                    ->options(PrivacyRequestStatus::class),

                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('deadline')
                    ->label('Deadline')
                    ->options([
                        'overdue' => 'Overdue',
                        'due_soon' => 'Due in 7 days',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'overdue' => PrivacyRequest::applyOverdue($query),
                            'due_soon' => PrivacyRequest::applyDueSoon($query),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No privacy requests')
            ->emptyStateDescription('Register a GDPR, CCPA/CPRA, PIPEDA or FERPA request to start its response clock.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Reference'),

                        TextEntry::make('regime')
                            ->badge(),

                        TextEntry::make('request_type')
                            ->label('Type')
                            ->badge(),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('received_at')
                            ->label('Received')
                            ->date(),

                        TextEntry::make('deadline')
                            ->label('Deadline')
                            ->state(fn (PrivacyRequest $record): string => self::deadlineLabel($record))
                            ->badge()
                            ->color(fn (PrivacyRequest $record): string => self::deadlineColor($record)),

                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unassigned'),

                        TextEntry::make('requester_name')
                            ->label('Requester')
                            ->placeholder('—'),

                        TextEntry::make('contact_reference')
                            ->label('Contact reference')
                            ->placeholder('—'),
                    ]),

                Section::make('Verification')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('identity_verified')
                            ->label('Identity verified')
                            ->boolean(),

                        TextEntry::make('verification_method')
                            ->label('Method')
                            ->placeholder('—'),

                        TextEntry::make('verified_at')
                            ->label('Verified on')
                            ->date()
                            ->placeholder('—'),
                    ]),

                Section::make('Deadlines')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('due_at')
                            ->label('Due')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('extended_at')
                            ->label('Extended due')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('extension_reason')
                            ->label('Extension reason')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Resolution')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('response_summary')
                            ->label('Response summary')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('denial_reason')
                            ->label('Denial reason')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('completed_at')
                            ->label('Completed')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('evidence_reference')
                            ->label('Evidence reference')
                            ->placeholder('—'),

                        TextEntry::make('evidence_sha256')
                            ->label('Evidence SHA-256')
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
            'index' => ListPrivacyRequests::route('/'),
            'create' => CreatePrivacyRequest::route('/create'),
            'view' => ViewPrivacyRequest::route('/{record}'),
            'edit' => EditPrivacyRequest::route('/{record}/edit'),
        ];
    }

    public static function deadlineLabel(PrivacyRequest $request): string
    {
        $due = $request->effectiveDueAt();

        if ($due === null) {
            return 'No deadline';
        }

        if ($request->status instanceof PrivacyRequestStatus && $request->status->isClosed()) {
            return $due->toDateString();
        }

        $days = $request->daysRemaining();

        if ($days === null) {
            return $due->toDateString();
        }

        return match (true) {
            $days < 0 => 'Overdue by '.abs($days).' day(s)',
            $days === 0 => 'Due today',
            default => $days.' day(s) left',
        };
    }

    public static function deadlineColor(PrivacyRequest $request): string
    {
        if ($request->status instanceof PrivacyRequestStatus && $request->status->isClosed()) {
            return 'gray';
        }

        $days = $request->daysRemaining();

        return match (true) {
            $days === null => 'gray',
            $days < 0 => 'danger',
            $days <= 7 => 'warning',
            default => 'success',
        };
    }
}
