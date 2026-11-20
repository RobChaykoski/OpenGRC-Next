<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenMetadata catalog evidence
    |--------------------------------------------------------------------------
    |
    | The catalog URL and read-only bot token are configured per evidence
    | source and stored encrypted. These settings describe how much of the
    | catalog one run may read and which tag namespaces count as a data
    | classification.
    |
    | OPENMETADATA_CLASSIFICATIONS is a comma-separated list of classification
    | root names, for example "PII,Sensitivity". A table is classified when the
    | table or any column carries a tag in one of these namespaces.
    |
    */
    'classification_names' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OPENMETADATA_CLASSIFICATIONS', 'PII'))
    ))),

    /*
    | Map a full OpenMetadata tag FQN to an OpenGRC data-classification code.
    | Unknown tags are still counted; they are never guessed into a code.
    */
    'tag_map' => [
        'PII.Sensitive' => 'personal_information',
        'PII.NonSensitive' => 'personal_information',
    ],

    'page_size' => (int) env('OPENMETADATA_PAGE_SIZE', 100),

    'max_pages' => (int) env('OPENMETADATA_MAX_PAGES', 50),
];
