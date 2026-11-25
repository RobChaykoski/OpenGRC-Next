<?php

namespace App\Filament\Resources\ModelArtifactResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * Per-file hashes for the artifact. Read-only: a hash is evidence of what was
 * inspected, not a field to correct by hand.
 */
class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Files';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->defaultSort('path')
            ->columns([
                TextColumn::make('path')
                    ->label('Path')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(80),

                TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): string => match ($state?->value) {
                        'weights' => 'info',
                        'template' => 'warning',
                        'tokenizer' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state))
                    ->sortable(),

                TextColumn::make('sha256')
                    ->label('SHA-256')
                    ->limit(16)
                    ->copyable()
                    ->placeholder('Not hashed')
                    ->fontFamily('mono'),
            ])
            ->paginated([25, 50, 100]);
    }
}
