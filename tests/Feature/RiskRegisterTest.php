<?php

namespace Tests\Feature;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use App\Enums\RiskStatus;
use App\Enums\UserRole;
use App\Models\Risk;
use App\Models\User;
use Database\Seeders\RiskCategoryTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskRegisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_risk_has_an_accountable_owner(): void
    {
        $owner = User::factory()->create([
            'name' => 'Risk Owner',
            'role' => UserRole::Editor,
        ]);

        $risk = Risk::create([
            'code' => 'RISK-001',
            'name' => 'Unpatched internet-facing service',
            'description' => 'A public service is running an unsupported version.',
            'status' => RiskStatus::NOT_ASSESSED,
            'owner_id' => $owner->id,
            'inherent_likelihood' => 4,
            'inherent_impact' => 5,
            'residual_likelihood' => 2,
            'residual_impact' => 4,
        ]);

        $this->assertTrue($risk->owner->is($owner));
        $this->assertSame($owner->id, $risk->fresh()->owner_id);
    }

    #[Test]
    public function a_risk_may_be_unassigned_while_it_is_triaged(): void
    {
        $risk = Risk::create([
            'code' => 'RISK-002',
            'name' => 'Unassigned risk',
            'status' => RiskStatus::NOT_ASSESSED,
        ]);

        $this->assertNull($risk->owner_id);
        $this->assertNull($risk->owner);
    }

    #[Test]
    public function risk_categories_are_seeded_as_taxonomy_terms(): void
    {
        $this->seed(RiskCategoryTaxonomySeeder::class);

        $root = Taxonomy::where('slug', 'risk-category')->whereNull('parent_id')->first();

        $this->assertNotNull($root);
        $this->assertSame(7, $root->children()->count());
        $this->assertDatabaseHas('taxonomies', ['slug' => 'risk-cybersecurity', 'parent_id' => $root->id]);

        $this->seed(RiskCategoryTaxonomySeeder::class);

        $this->assertSame(7, $root->children()->count());
    }
}
