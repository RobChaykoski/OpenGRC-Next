<?php

namespace App\Filament\Admin\Resources;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Filament\Admin\Resources\AccessRuleResource\Pages\CreateAccessRule;
use App\Filament\Admin\Resources\AccessRuleResource\Pages\EditAccessRule;
use App\Filament\Admin\Resources\AccessRuleResource\Pages\ListAccessRules;
use App\Models\AccessRule;
use App\Services\Access\AccessRuleLockoutGuard;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Admin surface for platform-wide IP and email-domain rules (ADR 0047).
 *
 * The form refuses a rule that would lock the current administrator out. The
 * table shows consequence fields — effect, scope, active and expiry — before
 * the note, because those decide access.
 */
class AccessRuleResource extends Resource
{
    protected static ?string $model = AccessRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 47;

    public static function getNavigationLabel(): string
    {
        return __('access-rules.navigation.label');
    }

    public static function getModelLabel(): string
    {
        return __('access-rules.model.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('access-rules.model.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('access-rules.form.section'))
                    ->description(__('access-rules.form.description'))
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label(__('access-rules.form.type'))
                            ->options(AccessRuleType::class)
                            ->default(AccessRuleType::Ip->value)
                            ->required()
                            ->live(),
                        Select::make('effect')
                            ->label(__('access-rules.form.effect'))
                            ->options(AccessRuleEffect::class)
                            ->default(AccessRuleEffect::Allow->value)
                            ->required()
                            ->helperText(__('access-rules.form.effect_help')),
                        Select::make('scope')
                            ->label(__('access-rules.form.scope'))
                            ->options(AccessRuleScope::class)
                            ->default(AccessRuleScope::Access->value)
                            ->required()
                            ->helperText(__('access-rules.form.scope_help')),
                        TextInput::make('value')
                            ->label(fn (Get $get): string => $get('type') === AccessRuleType::EmailDomain->value
                                ? __('access-rules.form.value_domain')
                                : __('access-rules.form.value_ip'))
                            ->placeholder(fn (Get $get): string => $get('type') === AccessRuleType::EmailDomain->value
                                ? 'example.com or *.example.com'
                                : '203.0.113.10 or 203.0.113.0/24')
                            ->required()
                            ->maxLength(191)
                            ->helperText(fn (Get $get): string => $get('type') === AccessRuleType::EmailDomain->value
                                ? __('access-rules.form.value_domain_help')
                                : __('access-rules.form.value_ip_help')),
                        TextInput::make('note')
                            ->label(__('access-rules.form.note'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        DateTimePicker::make('expires_at')
                            ->label(__('access-rules.form.expires_at'))
                            ->seconds(false)
                            ->helperText(__('access-rules.form.expires_help')),
                        Toggle::make('is_active')
                            ->label(__('access-rules.form.active'))
                            ->default(true)
                            ->helperText(__('access-rules.form.active_help')),
                        Placeholder::make('precedence')
                            ->label(__('access-rules.form.precedence_label'))
                            ->content(fn (): HtmlString => new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-300">'.e(__('access-rules.form.precedence')).'</p>'
                            ))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            ->defaultSort('scope')
            ->emptyStateHeading(__('access-rules.table.empty_heading'))
            ->emptyStateDescription(__('access-rules.table.empty_description'))
            ->columns([
                TextColumn::make('value')
                    ->label(__('access-rules.table.value'))
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                TextColumn::make('type')
                    ->label(__('access-rules.table.type'))
                    ->badge()
                    ->formatStateUsing(fn (AccessRuleType $state): string => $state->getLabel()),
                TextColumn::make('effect')
                    ->label(__('access-rules.table.effect'))
                    ->badge()
                    ->formatStateUsing(fn (AccessRuleEffect $state): string => $state->getLabel())
                    ->color(fn (AccessRuleEffect $state): string|array|null => $state->getColor()),
                TextColumn::make('scope')
                    ->label(__('access-rules.table.scope'))
                    ->formatStateUsing(fn (AccessRuleScope $state): string => $state->getLabel())
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('access-rules.table.active'))
                    ->boolean(),
                TextColumn::make('expires_at')
                    ->label(__('access-rules.table.expires_at'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder(__('access-rules.table.never'))
                    ->sortable(),
                TextColumn::make('note')
                    ->label(__('access-rules.table.note'))
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('access-rules.table.updated_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->label(__('access-rules.table.scope'))
                    ->options(AccessRuleScope::class),
                SelectFilter::make('effect')
                    ->label(__('access-rules.table.effect'))
                    ->options(AccessRuleEffect::class),
                SelectFilter::make('type')
                    ->label(__('access-rules.table.type'))
                    ->options(AccessRuleType::class),
                TernaryFilter::make('is_active')
                    ->label(__('access-rules.table.active')),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label(fn (AccessRule $record): string => $record->is_active
                        ? __('access-rules.actions.disable')
                        : __('access-rules.actions.enable'))
                    ->icon(fn (AccessRule $record): string => $record->is_active
                        ? 'heroicon-o-pause-circle'
                        : 'heroicon-o-play-circle')
                    ->color(fn (AccessRule $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (AccessRule $record): string => $record->is_active
                        ? __('access-rules.actions.disable_confirm')
                        : __('access-rules.actions.enable_confirm'))
                    ->action(function (AccessRule $record): void {
                        $candidate = clone $record;
                        $candidate->is_active = ! $record->is_active;

                        if (app(AccessRuleLockoutGuard::class)
                            ->wouldLockOutCurrentUser($candidate, $record->getKey())) {
                            Notification::make()
                                ->title(__('access-rules.lockout_title'))
                                ->body(__('access-rules.lockout'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active
                                ? __('access-rules.actions.enabled')
                                : __('access-rules.actions.disabled'))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->action(function (AccessRule $record): void {
                        if (app(AccessRuleLockoutGuard::class)->wouldLockOutWithout($record)) {
                            Notification::make()
                                ->title(__('access-rules.lockout_title'))
                                ->body(__('access-rules.lockout'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();
                    })
                    ->modalDescription(__('access-rules.actions.delete_confirm')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessRules::route('/'),
            'create' => CreateAccessRule::route('/create'),
            'edit' => EditAccessRule::route('/{record}/edit'),
        ];
    }
}
