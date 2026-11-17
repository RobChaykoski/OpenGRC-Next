<?php

namespace Database\Seeders\Demo;

use App\Models\Application;
use App\Models\Asset;
use App\Models\Certification;
use App\Models\Control;
use App\Models\Implementation;
use App\Models\Policy;
use App\Models\Program;
use App\Models\Risk;
use App\Models\Standard;
use App\Models\SurveyTemplate;
use App\Models\TrustCenterDocument;
use App\Models\User;
use App\Models\Vendor;
use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;

/**
 * Shared context for Demo seeders.
 * Holds all created models that need to be referenced across seeders.
 */
class DemoContext
{
    public Faker $faker;

    /** @var array<User> */
    public array $users = [];

    /** @var array<Vendor> */
    public array $vendors = [];

    /** @var array<Program> */
    public array $programs = [];

    /** @var array<Standard> */
    public array $standards = [];

    /** @var array<Control> */
    public array $controls = [];

    /** @var array<Control> */
    public array $tscControls = [];

    /** @var array<Implementation> */
    public array $implementations = [];

    /** @var array<Policy> */
    public array $policies = [];

    /** @var array<Risk> */
    public array $risks = [];

    /** @var array<Application> */
    public array $applications = [];

    /** @var array<Asset> */
    public array $assets = [];

    /** @var array<SurveyTemplate> */
    public array $surveyTemplates = [];

    /** @var array<Certification> */
    public array $certifications = [];

    /** @var array<TrustCenterDocument> */
    public array $trustCenterDocuments = [];

    public function __construct()
    {
        $this->faker = FakerFactory::create();
    }
}
