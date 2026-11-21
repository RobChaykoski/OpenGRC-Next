<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security architecture functions
    |--------------------------------------------------------------------------
    |
    | The canonical catalogue describes outcomes by risk domain and category.
    | This map places them in the six NIST CSF 2.0 functions for the
    | architecture view. It is a presentation map, not a control assessment:
    | an outcome appears once in the function its domain names.
    |
    | The first matching domain wins. Unknown domains fall back to `protect`.
    |
    */

    'functions' => [
        'govern' => [
            'domains' => ['Governance', 'People', 'Vendor Management', 'Privacy'],
        ],
        'identify' => [
            'domains' => ['Risk', 'Asset Management'],
        ],
        'protect' => [
            'domains' => [
                'Identity', 'Data Protection', 'Application Security',
                'Vulnerability Management', 'Operational Technology',
                'Access Control', 'Cloud Security',
            ],
        ],
        'detect' => [
            'domains' => ['Monitoring'],
        ],
        'respond' => [
            'domains' => ['Security Operations'],
        ],
        'recover' => [
            'domains' => ['Business Continuity'],
        ],
    ],

    'default_function' => 'protect',

];
