<?php

return [
    'check' => [
        'evidence' => 'Evidencijski registar',
        'audit' => 'Revizijski registar',
    ],

    'summary' => [
        'not_installed' => ':check još nije instaliran; pokrenite migracije.',
        'unreadable' => ':check nije moguće pročitati; pogledajte zapisnik aplikacije.',
        'evidence_passed' => 'Provjereno :count zapisa dokaza; lanac je ispravan.',
        'evidence_warned' => 'Provjereno :count zapisa dokaza; :warnings upozorenja.',
        'evidence_failed' => 'Provjereno :count zapisa dokaza; pronađeno :problems problema.',
        'audit_passed' => 'Revizijski registar ispravan kroz :total zapisa.',
        'audit_pruned' => ':missing zrcaljenih zapisa aktivnosti je uklonjeno; zapisi registra ostaju.',
        'audit_broken' => 'Lanac revizijskog registra prekinut je na zapisu #:entry.',
        'audit_altered' => ':count zapisa aktivnosti više ne odgovara revizijskom registru.',
    ],

    'command' => [
        'unknown_check' => 'Nepoznata provjera [:check]. Koristite evidence, audit ili all.',
        'pass' => 'USPJEH',
        'warn' => 'UPOZORENJE',
        'fail' => 'NEUSPJEH',
        'detail' => 'Provjereno :time u :duration ms.',
    ],

    'widget' => [
        'title' => 'Cjelovitost zapisa',
        'value' => [
            'pass' => 'Ispravno',
            'warn' => 'Pažnja',
            'fail' => 'Neuspjelo',
            'never' => 'Nije pokrenuto',
        ],
        'description' => [
            'never' => 'Pokrenite php artisan opengrc:integrity:verify',
            'last_run' => 'Lanci dokaza i revizije; posljednja provjera :time',
            'stale' => 'Posljednja provjera :time; raspored se možda zaustavio',
            'issue' => ':summary',
        ],
    ],
];
