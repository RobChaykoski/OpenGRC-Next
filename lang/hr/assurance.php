<?php

return [
    'navigation' => [
        'label' => 'Matrica pokrivenosti',
        'group' => 'Temelji',
    ],
    'page' => [
        'title' => 'Matrica pokrivenosti',
    ],
    'framework' => [
        'label' => 'Okvir',
        'help' => 'Odaberite okvir da vidite ishode koje zahtijeva i što ih trenutačno podupire. Ova stranica prikazuje samo pregledane poveznice i dokaze; to nije ocjena usklađenosti. Ishodi bez povezane kontrole prikazuju se kao posao za pregled, a ne kao neuspjeh.',
        'none' => 'Nema okvira u katalogu. Pokrenite php artisan opengrc:catalog:sync.',
    ],
    'columns' => [
        'code' => 'Kod',
        'outcome' => 'Ishod',
        'category' => 'Kategorija',
        'priority' => 'Prioritet',
        'automation' => 'Automatizacija',
        'controls' => 'Kontrole',
        'policies' => 'Politike',
        'evidence' => 'Dokazi',
        'status' => 'Status',
    ],
    'priority' => [
        'high' => 'Visok',
        'medium' => 'Srednji',
        'low' => 'Nizak',
    ],
    'automation' => [
        'high' => 'Visoka',
        'medium' => 'Srednja',
        'low' => 'Niska',
    ],
    'status' => [
        'covered' => 'Povezano + s dokazima',
        'no_evidence' => 'Nedostaju dokazi',
        'no_control' => 'Nema pregledane kontrole',
    ],
    'filters' => [
        'gaps' => 'Samo nedostaci',
        'only_gaps' => 'Samo ishodi koje treba pregledati',
        'only_gaps_helper' => 'Prikazuje ishode bez povezane kontrole ili s povezanom kontrolom bez dokaza.',
    ],
    'not_set' => 'Nije postavljeno',
    'latest_evidence' => 'Posljednji dokaz: :date',
    'empty' => [
        'heading' => 'Nema ishoda za ovaj okvir',
        'description' => 'Uvezite okvir ili proširite config/framework_scoping.php, zatim pokrenite opengrc:catalog:sync.',
    ],
];
