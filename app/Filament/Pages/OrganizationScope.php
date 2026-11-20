<?php

namespace App\Filament\Pages;

use App\Services\Scoping\DataClassificationCatalog;
use App\Services\Scoping\FrameworkScopingService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Organization-profile scoping.
 *
 * The page is a read-only query over the existing FrameworkScopingService. It
 * recommends frameworks and controls from the profile; it does not import a
 * framework or change the control catalogue.
 *
 * @property mixed $form
 */
class OrganizationScope extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'organization-scope';

    protected string $view = 'filament.pages.organization-scope';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $assessment = null;

    public function mount(): void
    {
        $this->getSchema('form')?->fill([
            'industry' => 'technology',
            'country' => 'CA',
            'employees' => '50-249',
            'revenue' => '10m_50m',
            'data_classifications' => [],
        ]);
    }

    public static function getNavigationLabel(): string
    {
        return __('scoping.navigation');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('scoping.navigation_group');
    }

    public function getTitle(): string
    {
        return __('scoping.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('scoping.sections.organization'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('scoping.form.name'))
                            ->maxLength(255)
                            ->placeholder(__('scoping.form.name_placeholder')),

                        Select::make('industry')
                            ->label(__('scoping.form.industry'))
                            ->options(self::industryOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('data_classifications', [])),

                        Select::make('country')
                            ->label(__('scoping.form.country'))
                            ->options(self::countryOptions())
                            ->required()
                            ->live(),

                        Select::make('province')
                            ->label(__('scoping.form.province'))
                            ->options(self::subdivisionOptions())
                            ->visible(fn (Get $get): bool => in_array($get('country'), ['CA', 'US'], true))
                            ->placeholder(__('scoping.form.province_placeholder')),

                        Select::make('employees')
                            ->label(__('scoping.form.employees'))
                            ->options(self::employeeOptions()),

                        Select::make('revenue')
                            ->label(__('scoping.form.revenue'))
                            ->options(self::revenueOptions()),
                    ]),

                Section::make(__('scoping.sections.data'))
                    ->description(__('scoping.sections.data_help'))
                    ->schema([
                        CheckboxList::make('data_classifications')
                            ->label(__('scoping.form.data_classifications'))
                            ->options(fn (Get $get): array => app(DataClassificationCatalog::class)->options($get('industry')))
                            ->columns(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function assess(): void
    {
        $this->validate();

        $profile = $this->getSchema('form')?->getState() ?? [];

        $this->assessment = app(FrameworkScopingService::class)->assess($profile);
    }

    /**
     * @return array<string, string>
     */
    public static function industryOptions(): array
    {
        return [
            'healthcare' => __('scoping.industries.healthcare'),
            'financial_services' => __('scoping.industries.financial_services'),
            'technology' => __('scoping.industries.technology'),
            'government' => __('scoping.industries.government'),
            'critical_infrastructure' => __('scoping.industries.critical_infrastructure'),
            'education' => __('scoping.industries.education'),
            'energy' => __('scoping.industries.energy'),
            'manufacturing' => __('scoping.industries.manufacturing'),
            'retail' => __('scoping.industries.retail'),
            'other' => __('scoping.industries.other'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function countryOptions(): array
    {
        return [
            'CA' => __('scoping.countries.CA'),
            'US' => __('scoping.countries.US'),
            'EU' => __('scoping.countries.EU'),
            'UK' => __('scoping.countries.UK'),
            'AU' => __('scoping.countries.AU'),
            'other' => __('scoping.countries.other'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function subdivisionOptions(): array
    {
        return [
            'CA-ON' => __('scoping.subdivisions.CA-ON'),
            'CA-QC' => __('scoping.subdivisions.CA-QC'),
            'CA-BC' => __('scoping.subdivisions.CA-BC'),
            'CA-AB' => __('scoping.subdivisions.CA-AB'),
            'CA-MB' => __('scoping.subdivisions.CA-MB'),
            'CA-SK' => __('scoping.subdivisions.CA-SK'),
            'CA-NS' => __('scoping.subdivisions.CA-NS'),
            'CA-NB' => __('scoping.subdivisions.CA-NB'),
            'CA-NL' => __('scoping.subdivisions.CA-NL'),
            'CA-PE' => __('scoping.subdivisions.CA-PE'),
            'US-NY' => __('scoping.subdivisions.US-NY'),
            'US-CA' => __('scoping.subdivisions.US-CA'),
            'US-TX' => __('scoping.subdivisions.US-TX'),
            'US-WA' => __('scoping.subdivisions.US-WA'),
            'US-FL' => __('scoping.subdivisions.US-FL'),
            'US-IL' => __('scoping.subdivisions.US-IL'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function employeeOptions(): array
    {
        return [
            '1-49' => __('scoping.employees.small'),
            '50-249' => __('scoping.employees.medium'),
            '250-999' => __('scoping.employees.large'),
            '1000+' => __('scoping.employees.enterprise'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function revenueOptions(): array
    {
        return [
            'under_10m' => __('scoping.revenue.under_10m'),
            '10m_50m' => __('scoping.revenue.10m_50m'),
            '50m_250m' => __('scoping.revenue.50m_250m'),
            '250m_plus' => __('scoping.revenue.250m_plus'),
        ];
    }
}
