<?php

namespace App\Filament\Resources\VendorResource\RelationManagers;

use App\Enums\VendorRiskRating;
use App\Models\VendorSubcontractor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The vendor's subcontractors: who else touches the service or the data.
 *
 * This is a register, not a supply-chain graph. A subcontractor's own suppliers
 * are out of scope; record the next tier as a separate vendor if you manage it.
 */
class SubcontractorsRelationManager extends RelationManager
{
    protected static string $relationship = 'subcontractors';

    protected static ?string $title = 'Subcontractors';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('service')
                    ->label('Service')
                    ->helperText('What they do for the vendor.')
                    ->maxLength(255),

                TextInput::make('country')
                    ->label('Country')
                    ->maxLength(64),

                Toggle::make('accesses_personal_data')
                    ->label('Accesses personal data')
                    ->helperText('Tick when the subcontractor can see or process personal data.'),

                Select::make('risk_rating')
                    ->label('Risk rating')
                    ->options(VendorRiskRating::class),

                DatePicker::make('last_reviewed_at')
                    ->label('Last reviewed'),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('service')
                    ->label('Service')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('country')
                    ->label('Country')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('accesses_personal_data')
                    ->label('Personal data')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('risk_rating')
                    ->label('Risk')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?VendorRiskRating $state): ?string => $state?->getLabel())
                    ->color(fn (?VendorRiskRating $state): string|array|null => $state?->getColor()),

                TextColumn::make('last_reviewed_at')
                    ->label('Last reviewed')
                    ->date()
                    ->placeholder('Never')
                    ->color(fn (VendorSubcontractor $record): string => $record->isReviewOverdue() ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('accesses_personal_data')
                    ->label('Personal data'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No subcontractors')
            ->emptyStateDescription('Record a fourth party that provides part of this vendor\'s service or touches your data.');
    }
}
