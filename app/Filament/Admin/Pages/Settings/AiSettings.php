<?php

namespace App\Filament\Admin\Pages\Settings;

use App\Enums\AiProvider;
use App\Filament\Admin\Pages\Settings\Schemas\AiQuotaSchema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;

class AiSettings extends BaseSettings
{
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-variable';

    public static function canAccess(): bool
    {
        if (auth()->check() && auth()->user()->can('Manage Preferences')) {
            return true;
        }

        return false;
    }

    public static function getNavigationGroup(): string
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.settings.ai_settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('AiSettings')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Configuration')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make('AI Configuration')
                                    ->schema([
                                        Toggle::make('ai.enabled')
                                            ->label('Enable AI Suggestions')
                                            ->default(false),
                                        Select::make('ai.provider')
                                            ->label('AI Provider')
                                            ->options(
                                                collect(AiProvider::cases())
                                                    ->mapWithKeys(fn (AiProvider $provider) => [$provider->value => $provider->getLabel()])
                                                    ->toArray()
                                            )
                                            ->default(AiProvider::OpenAI->value)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                                if ($state) {
                                                    $provider = AiProvider::tryFrom($state);
                                                    if ($provider) {
                                                        $set('ai.model', $provider->getDefaultModel());
                                                    }
                                                }
                                            })
                                            ->helperText('Select the AI provider to use for suggestions'),
                                        Select::make('ai.model')
                                            ->label('Model')
                                            ->options(function (Get $get): array {
                                                $providerValue = $get('ai.provider');
                                                $provider = $providerValue ? AiProvider::tryFrom($providerValue) : AiProvider::OpenAI;

                                                return $provider?->getModels() ?? [];
                                            })
                                            ->default(AiProvider::OpenAI->getDefaultModel())
                                            ->helperText('Select the model to use for AI suggestions'),
                                    ]),
                                Section::make('OpenAI')
                                    ->description('Configure OpenAI API access')
                                    ->collapsed(fn (Get $get): bool => $get('ai.provider') !== AiProvider::OpenAI->value)
                                    ->schema([
                                        TextInput::make('ai.openai_key')
                                            ->label('API Key')
                                            ->password()
                                            ->placeholder(function () {
                                                if (filled(setting('ai.openai_key'))) {
                                                    return '••••••••';
                                                }
                                                if (filled(config('ai.keys.openai'))) {
                                                    return 'Using key from .env';
                                                }

                                                return null;
                                            })
                                            ->helperText(function () {
                                                $hasStoredKey = filled(setting('ai.openai_key'));
                                                $hasEnvKey = filled(config('ai.keys.openai'));

                                                if ($hasStoredKey && $hasEnvKey) {
                                                    return 'API key is stored. Leave blank to clear and use the key from .env instead.';
                                                }
                                                if ($hasStoredKey) {
                                                    return 'API key is stored. Leave blank to clear it.';
                                                }
                                                if ($hasEnvKey) {
                                                    return 'API key provided via OPENAI_API_KEY in .env. Leave blank to use it, or enter a key here to override.';
                                                }

                                                return 'Enter your OpenAI API key';
                                            })
                                            ->dehydrateStateUsing(function ($state) {
                                                // If blank, clear the stored key (env fallback will be used)
                                                if (! filled($state)) {
                                                    return null;
                                                }

                                                return Crypt::encryptString($state);
                                            })
                                            ->afterStateHydrated(function (TextInput $component, $state) {
                                                $component->state(null);
                                            }),
                                    ]),
                                Section::make('DigitalOcean')
                                    ->description('Configure DigitalOcean GenAI API access')
                                    ->collapsed(fn (Get $get): bool => $get('ai.provider') !== AiProvider::DigitalOcean->value)
                                    ->schema([
                                        TextInput::make('ai.digitalocean_key')
                                            ->label('API Key')
                                            ->password()
                                            ->placeholder(function () {
                                                if (filled(setting('ai.digitalocean_key'))) {
                                                    return '••••••••';
                                                }
                                                if (filled(config('ai.keys.digitalocean'))) {
                                                    return 'Using key from .env';
                                                }

                                                return null;
                                            })
                                            ->helperText(function () {
                                                $hasStoredKey = filled(setting('ai.digitalocean_key'));
                                                $hasEnvKey = filled(config('ai.keys.digitalocean'));

                                                if ($hasStoredKey && $hasEnvKey) {
                                                    return 'API key is stored. Leave blank to clear and use the key from .env instead.';
                                                }
                                                if ($hasStoredKey) {
                                                    return 'API key is stored. Leave blank to clear it.';
                                                }
                                                if ($hasEnvKey) {
                                                    return 'API key provided via DIGITALOCEAN_AI_KEY in .env. Leave blank to use it, or enter a key here to override.';
                                                }

                                                return 'Enter your DigitalOcean API key';
                                            })
                                            ->dehydrateStateUsing(function ($state) {
                                                // If blank, clear the stored key (env fallback will be used)
                                                if (! filled($state)) {
                                                    return null;
                                                }

                                                return Crypt::encryptString($state);
                                            })
                                            ->afterStateHydrated(function (TextInput $component, $state) {
                                                $component->state(null);
                                            }),
                                    ]),
                            ]),
                        Tab::make('Quota Usage')
                            ->icon('heroicon-o-chart-bar')
                            ->schema(AiQuotaSchema::schema()),
                    ]),
            ]);
    }
}
