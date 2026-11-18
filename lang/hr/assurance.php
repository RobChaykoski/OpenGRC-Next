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
    'score' => [
        'heading' => 'Ocjena pokrivenosti',
        'total' => 'Ocjena',
        'counts' => ':reviewed od :outcomes ishoda pregledano',
        'trend' => ':change kroz :days dana',
        'no_trend' => 'Još nema trenda — dnevna snimka ga gradi.',
        'weight' => ':weight% težine',
        'help' => 'Ponderirani prikaz pregledanih poveznica i kvalitete dokaza. To nije postotak usklađenosti; pročitajte komponente prije djelovanja.',
        'unavailable' => 'Još nema ocjene — ovaj okvir nema ishoda u katalogu.',
        'components' => [
            'reviewed' => 'Pregledano',
            'evidence' => 'Dokazi',
            'quality' => 'Kvaliteta',
            'automation' => 'Automatizacija',
            'monitoring' => 'Nadzor',
        ],
    ],
    'not_set' => 'Nije postavljeno',
    'latest_evidence' => 'Posljednji dokaz: :date',
    'empty' => [
        'heading' => 'Nema ishoda za ovaj okvir',
        'description' => 'Uvezite okvir ili proširite config/framework_scoping.php, zatim pokrenite opengrc:catalog:sync.',
    ],
];
