<?php

namespace Database\Seeders;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The risk types a register is usually segmented by. Terms are seeded once and
 * can be renamed or extended; a risk can carry more than one category.
 */
class RiskCategoryTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $parent = Taxonomy::firstOrCreate(
            [
                'slug' => 'risk-category',
                'type' => 'risk',
            ],
            [
                'name' => 'Risk Category',
                'description' => 'The type of risk: strategic, operational, compliance, and so on',
                'sort_order' => 1,
            ]
        );

        $categories = [
            ['name' => 'Strategic', 'description' => 'Affects business goals, direction, or competitive position'],
            ['name' => 'Financial', 'description' => 'Affects money, investments, liquidity, or reporting'],
            ['name' => 'Operational', 'description' => 'Caused by failing systems, processes, or people'],
            ['name' => 'Compliance', 'description' => 'Arises from not meeting laws, regulations, or contracts'],
            ['name' => 'Cybersecurity', 'description' => 'Arises from digital attacks, breaches, or data loss'],
            ['name' => 'Reputational', 'description' => 'Damages trust, brand, or public confidence'],
            ['name' => 'Environmental', 'description' => 'Relates to environmental impact or sustainability obligations'],
        ];

        foreach ($categories as $index => $category) {
            Taxonomy::firstOrCreate(
                [
                    'slug' => 'risk-'.Str::slug($category['name']),
                    'type' => 'risk',
                    'parent_id' => $parent->id,
                ],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
