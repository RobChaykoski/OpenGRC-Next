<?php

namespace App\Filament\Resources;

use App\Enums\AttestationOutcome;
use App\Enums\AttestationStatus;
use App\Enums\AttestationType;
use App\Filament\Resources\AttestationReportResource\Pages\CreateAttestationReport;
use App\Filament\Resources\AttestationReportResource\Pages\EditAttestationReport;
use App\Filament\Resources\AttestationReportResource\Pages\ListAttestationReports;
use App\Filament\Resources\AttestationReportResource\Pages\ViewAttestationReport;
use App\Models\AttestationReport;
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
 * Assurance report and attestation register.
 *
 * A report is evidence about controls, not a control catalogue. The register
 * keeps the scope, period, auditor, opinion, exceptions, bridge letter and next
 * review so an auditor can inspect the claim without opening the report body.
 */
class AttestationReportResource extends Resource
{
    protected static ?string $model = AttestationReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Assurance reports';

    protected static ?string $modelLabel = 'Assurance report';

    protected static ?string $pluralModelLabel = 'Assurance reports';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Report')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Report name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label('Report type')
                            ->options(AttestationType::class)
                            ->required(),

                        TextInput::make('provider')
                            ->label('Auditor or certifying body')
                            ->maxLength(255),

                        TextInput::make('framework_code')
                            ->label('Framework code')
                            ->helperText('For example TSC2017a, ISO-27001 or PCI-DSS-4.0.1. Optional.')
                            ->maxLength(64),

                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),

                        Textarea::make('subject')
                            ->label('Scope')
                            ->helperText('The system, service or population covered. Do not paste the report body.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Coverage and dates')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Period start'),

                        DatePicker::make('period_end')
                            ->label('Period end'),

                        DatePicker::make('issued_at')
                            ->label('Issued'),

                        DatePicker::make('expires_at')
                            ->label('Expires')
                            ->helperText('Leave blank for a report with no explicit expiry; set a review date instead.'),

                        DatePicker::make('bridge_letter_date')
                            ->label('Bridge letter'),

                        DatePicker::make('next_review_at')
                            ->label('Next review'),
                    ]),

                Section::make('Outcome and evidence')
                    ->columns(2)
                    ->schema([
                        Select::make('outcome')
                            ->label('Outcome')
                            ->options(AttestationOutcome::class)
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options(AttestationStatus::class)
                            ->default(AttestationStatus::Current)
                            ->required(),

                        Textarea::make('summary')
                            ->label('Summary')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('exceptions')
                            ->label('Exceptions or qualifications')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('document_reference')
                            ->label('Document reference')
                            ->helperText('A private store path or reference, not a public URL.')
                            ->maxLength(255),

                        TextInput::make('document_sha256')
                            ->label('Document SHA-256')
                            ->maxLength(64),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['vendor', 'owner']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Report')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('provider')
                    ->label('Provider')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('period_end')
                    ->label('Period end')
                    ->date()
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiry')
                    ->color(fn (AttestationReport $record): string => $record->isExpired() ? 'danger' : 'gray'),

                TextColumn::make('outcome')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_review_at')
                    ->label('Next review')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(AttestationType::class),

                SelectFilter::make('outcome')
                    ->options(AttestationOutcome::class),

                SelectFilter::make('status')
                    ->options(AttestationStatus::class),

                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('review')
                    ->label('Review')
                    ->options([
                        'expired' => 'Expired',
                        'due' => 'Review due in 60 days',
                        'no_expiry' => 'No expiry set',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<', today()),
                            'due' => $query->whereNotNull('next_review_at')->whereBetween('next_review_at', [today(), today()->addDays(60)]),
                            'no_expiry' => $query->whereNull('expires_at'),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->emptyStateHeading('No assurance reports')
            ->emptyStateDescription('Register a SOC report, ISO certificate, PCI AOC or CMMC affirmation to make the assurance claim inspectable.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Report')
                            ->columnSpanFull(),

                        TextEntry::make('type')
                            ->badge(),

                        TextEntry::make('provider')
                            ->label('Provider')
                            ->placeholder('—'),

                        TextEntry::make('framework_code')
                            ->label('Framework code')
                            ->placeholder('—'),

                        TextEntry::make('vendor.name')
                            ->label('Vendor')
                            ->placeholder('Own organisation'),

                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('Unassigned'),

                        TextEntry::make('subject')
                            ->label('Scope')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Coverage and outcome')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('period_start')
                            ->label('Period start')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('period_end')
                            ->label('Period end')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('issued_at')
                            ->label('Issued')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->date()
                            ->placeholder('No expiry'),

                        TextEntry::make('bridge_letter_date')
                            ->label('Bridge letter')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('next_review_at')
                            ->label('Next review')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('outcome')
                            ->badge(),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('document_reference')
                            ->label('Document reference')
                            ->placeholder('—'),
                    ]),

                Section::make('Summary and exceptions')
                    ->schema([
                        TextEntry::make('summary')
                            ->label('Summary')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('exceptions')
                            ->label('Exceptions or qualifications')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('document_sha256')
                            ->label('Document SHA-256')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttestationReports::route('/'),
            'create' => CreateAttestationReport::route('/create'),
            'view' => ViewAttestationReport::route('/{record}'),
            'edit' => EditAttestationReport::route('/{record}/edit'),
        ];
    }
}
