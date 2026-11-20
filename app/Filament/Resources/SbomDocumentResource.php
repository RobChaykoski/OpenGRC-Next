<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SbomDocumentResource\Pages\ListSbomDocuments;
use App\Filament\Resources\SbomDocumentResource\Pages\ViewSbomDocument;
use App\Filament\Resources\SbomDocumentResource\RelationManagers\ComponentsRelationManager;
use App\Filament\Resources\SbomDocumentResource\RelationManagers\VulnerabilitiesRelationManager;
use App\Models\SbomDocument;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Software inventory: imported SBOMs, their components and OSV advisories.
 *
 * Documents are imported by the CLI, not created in the UI. This resource is a
 * read-only view of the inventory and the findings it raised.
 */
class SbomDocumentResource extends Resource
{
    protected static ?string $model = SbomDocument::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 38;

    protected static ?string $navigationLabel = 'Software inventory';

    protected static ?string $modelLabel = 'SBOM document';

    protected static ?string $pluralModelLabel = 'SBOM documents';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['application', 'asset'])
                ->withCount([
                    'components',
                    'vulnerabilities as open_vulnerabilities_count' => fn (Builder $vulnerabilities): Builder => $vulnerabilities->where('status', 'open'),
                ]))
            ->defaultSort('imported_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (SbomDocument $record): string => $record->reference())
                    ->searchable(false),

                TextColumn::make('name')
                    ->label('SBOM')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('application.name')
                    ->label('Application')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('asset.name')
                    ->label('Asset')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('components_count')
                    ->label('Components')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('open_vulnerabilities_count')
                    ->label('Open advisories')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),

                TextColumn::make('imported_at')
                    ->label('Imported')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('freshness')
                    ->label('Age')
                    ->getStateUsing(fn (SbomDocument $record): string => $record->imported_at?->diffForHumans() ?? '—')
                    ->badge()
                    ->color(fn (SbomDocument $record): string => self::ageColor($record)),
            ])
            ->filters([
                SelectFilter::make('application_id')
                    ->label('Application')
                    ->relationship('application', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('asset_id')
                    ->label('Asset')
                    ->relationship('asset', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('review')
                    ->label('Review')
                    ->options([
                        'vulnerable' => 'Has open advisories',
                        'stale' => 'Imported more than 30 days ago',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'vulnerable' => $query->whereHas('vulnerabilities', fn (Builder $inner): Builder => $inner->where('status', 'open')),
                            'stale' => $query->where('imported_at', '<', now()->subDays(30)),
                            default => $query,
                        };
                    }),
            ])
            ->emptyStateHeading('No SBOMs imported')
            ->emptyStateDescription('Run php artisan opengrc:sbom:import to add a CycloneDX software inventory.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SBOM')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->columnSpanFull(),

                        TextEntry::make('reference')
                            ->label('Reference')
                            ->state(fn (SbomDocument $record): string => $record->reference()),

                        TextEntry::make('format')
                            ->label('Format'),

                        TextEntry::make('spec_version')
                            ->label('Spec version')
                            ->placeholder('—'),

                        TextEntry::make('application.name')
                            ->label('Application')
                            ->placeholder('—'),

                        TextEntry::make('asset.name')
                            ->label('Asset')
                            ->placeholder('—'),

                        TextEntry::make('imported_at')
                            ->label('Imported')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('importedBy.name')
                            ->label('Imported by')
                            ->placeholder('CLI'),

                        TextEntry::make('component_count')
                            ->label('Components'),

                        TextEntry::make('open_vulnerabilities')
                            ->label('Open advisories')
                            ->state(fn (SbomDocument $record): int => $record->vulnerabilities()->where('status', 'open')->count())
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),

                        TextEntry::make('document_sha256')
                            ->label('Document SHA-256')
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('source')
                            ->label('Source')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            'components' => ComponentsRelationManager::class,
            'vulnerabilities' => VulnerabilitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSbomDocuments::route('/'),
            'view' => ViewSbomDocument::route('/{record}'),
        ];
    }

    public static function ageColor(SbomDocument $document): string
    {
        if ($document->imported_at === null) {
            return 'gray';
        }

        return match (true) {
            $document->imported_at->lt(now()->subDays(90)) => 'danger',
            $document->imported_at->lt(now()->subDays(30)) => 'warning',
            default => 'success',
        };
    }
}
