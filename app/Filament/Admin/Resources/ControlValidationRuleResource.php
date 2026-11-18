<?php

namespace App\Filament\Admin\Resources;

use App\Enums\EvidenceSeverity;
use App\Filament\Admin\Resources\ControlValidationRuleResource\Pages\CreateControlValidationRule;
use App\Filament\Admin\Resources\ControlValidationRuleResource\Pages\EditControlValidationRule;
use App\Filament\Admin\Resources\ControlValidationRuleResource\Pages\ListControlValidationRules;
use App\Filament\Admin\Resources\ControlValidationRuleResource\Pages\ViewControlValidationRule;
use App\Models\ControlValidationRule;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Admin-only editor for validation rules. A rule change alters what counts as
 * compliant, so it is a configuration action, not an editor's daily work.
 */
class ControlValidationRuleResource extends Resource
{
    protected static ?string $model = ControlValidationRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('evidence.rule.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('evidence.rule.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('evidence.rule.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('evidence.rule.sections.rule'))
                    ->columns(2)
                    ->schema([
                        Select::make('control_id')
                            ->label(__('evidence.rule.form.control'))
                            ->relationship('control', 'code')
                            ->searchable()
                            ->required(),

                        Select::make('source_id')
                            ->label(__('evidence.rule.form.source'))
                            ->relationship('source', 'name')
                            ->placeholder(__('evidence.rule.any_source'))
                            ->searchable(),

                        Select::make('severity')
                            ->label(__('evidence.rule.form.severity'))
                            ->options(EvidenceSeverity::class)
                            ->required()
                            ->default(EvidenceSeverity::Medium),

                        TextInput::make('version')
                            ->label(__('evidence.rule.form.version'))
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        Toggle::make('enabled')
                            ->label(__('evidence.rule.form.enabled'))
                            ->default(true),
                    ]),

                Section::make(__('evidence.rule.sections.definition'))
                    ->schema([
                        Textarea::make('definition')
                            ->label('')
                            ->required()
                            ->rows(14)
                            ->rules([
                                'json',
                                function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_string($value) || ! is_array(json_decode($value, true))) {
                                        $fail('The definition must be a JSON object with an assert list.');
                                    }
                                },
                            ])
                            ->formatStateUsing(fn ($state): string => self::prettyDefinition($state))
                            ->dehydrateStateUsing(fn ($state): mixed => is_string($state)
                                ? json_decode($state, true)
                                : $state)
                            ->helperText(__('evidence.rule.definition_help'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('control.code')
                    ->label(__('evidence.rule.form.control'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source.name')
                    ->label(__('evidence.rule.form.source'))
                    ->placeholder(__('evidence.rule.any_source'))
                    ->searchable(),

                TextColumn::make('severity')
                    ->label(__('evidence.rule.form.severity'))
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                    ->color(fn ($state): ?string => $state?->getColor())
                    ->searchable(false)
                    ->sortable(),

                TextColumn::make('version')
                    ->label(__('evidence.rule.form.version'))
                    ->sortable(),

                IconColumn::make('enabled')
                    ->label(__('evidence.rule.table.enabled'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('evidence.rule.table.updated_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('control_id')
                    ->label(__('evidence.rule.form.control'))
                    ->relationship('control', 'code')
                    ->searchable(),

                SelectFilter::make('source_id')
                    ->label(__('evidence.rule.form.source'))
                    ->relationship('source', 'name')
                    ->searchable(),

                SelectFilter::make('severity')
                    ->label(__('evidence.rule.form.severity'))
                    ->options(EvidenceSeverity::class),

                SelectFilter::make('enabled')
                    ->label(__('evidence.rule.form.enabled'))
                    ->options(['1' => 'Enabled', '0' => 'Disabled']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('control_id')
            ->emptyStateHeading(__('evidence.rule.empty_heading'))
            ->emptyStateDescription(__('evidence.rule.empty_description'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('evidence.rule.sections.rule'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('control.code')
                            ->label(__('evidence.rule.form.control')),

                        TextEntry::make('source.name')
                            ->label(__('evidence.rule.form.source'))
                            ->placeholder(__('evidence.rule.any_source')),

                        TextEntry::make('severity')
                            ->label(__('evidence.rule.form.severity'))
                            ->badge()
                            ->formatStateUsing(fn ($state): ?string => $state?->getLabel())
                            ->color(fn ($state): ?string => $state?->getColor()),

                        TextEntry::make('version')
                            ->label(__('evidence.rule.form.version')),

                        TextEntry::make('enabled')
                            ->label(__('evidence.rule.form.enabled'))
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ? 'Enabled' : 'Disabled')
                            ->color(fn ($state): string => $state ? 'success' : 'gray'),
                    ]),

                Section::make(__('evidence.rule.sections.definition'))
                    ->schema([
                        TextEntry::make('definition_json')
                            ->label('')
                            ->state(fn (ControlValidationRule $record): string => self::prettyDefinition($record->definition))
                            ->fontFamily(FontFamily::Mono)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-wrap break-words text-xs']),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListControlValidationRules::route('/'),
            'create' => CreateControlValidationRule::route('/create'),
            'view' => ViewControlValidationRule::route('/{record}'),
            'edit' => EditControlValidationRule::route('/{record}/edit'),
        ];
    }

    public static function prettyDefinition(mixed $definition): string
    {
        if (! is_array($definition)) {
            return is_string($definition) ? $definition : '{}';
        }

        return json_encode(
            $definition,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '{}';
    }
}
