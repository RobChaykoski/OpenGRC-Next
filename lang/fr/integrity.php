<?php

return [
    'check' => [
        'evidence' => 'Registre des preuves',
        'audit' => 'Registre d’audit',
    ],

    'summary' => [
        'not_installed' => ':check n’est pas encore installé ; exécutez les migrations.',
        'unreadable' => 'Impossible de lire :check ; consultez le journal de l’application.',
        'evidence_passed' => ':count enregistrement(s) de preuve vérifié(s) ; la chaîne est intacte.',
        'evidence_warned' => ':count enregistrement(s) de preuve vérifié(s) ; :warnings avertissement(s).',
        'evidence_failed' => ':count enregistrement(s) de preuve vérifié(s) ; :problems problème(s) détecté(s).',
        'audit_passed' => 'Registre d’audit intact sur :total entrées.',
        'audit_pruned' => ':missing ligne(s) d’activité mise(s) en miroir ont été élaguées ; les entrées du registre restent.',
        'audit_broken' => 'La chaîne du registre d’audit est rompue à l’entrée n° :entry.',
        'audit_altered' => ':count ligne(s) d’activité ne correspondent plus au registre d’audit.',
    ],

    'command' => [
        'unknown_check' => 'Vérification inconnue [:check]. Utilisez evidence, audit ou all.',
        'pass' => 'RÉUSSI',
        'warn' => 'AVERTISSEMENT',
        'fail' => 'ÉCHEC',
        'detail' => 'Vérifié à :time en :duration ms.',
    ],

    'widget' => [
        'title' => 'Intégrité des enregistrements',
        'value' => [
            'pass' => 'Intacte',
            'warn' => 'Attention',
            'fail' => 'Échec',
            'never' => 'Non exécutée',
        ],
        'description' => [
            'never' => 'Exécutez php artisan opengrc:integrity:verify',
            'last_run' => 'Chaînes de preuves et d’audit ; dernière vérification :time',
            'stale' => 'Dernière vérification :time ; la planification s’est peut-être arrêtée',
            'issue' => ':summary',
        ],
    ],
];
