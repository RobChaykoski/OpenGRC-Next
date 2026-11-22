<?php

return [

    'navigation' => [
        'label' => 'Overview',
        'group' => 'Access governance',
    ],

    'title' => 'Access governance',

    'intro' => [
        'body' => 'Registers the data that needs protection, the rules that decide access, and the decisions that were made. OpenGRC governs access; the enforcement point enforces it.',
        'no_decision' => 'The enforcement point must treat a missing decision as deny. A timeout is not permission.',
        'callout_title' => 'No decision means deny',
        'data_title' => 'Data',
        'data_body' => 'Where sensitive data lives, how it is classified, who owns it and whether the classification is still current.',
        'rules_title' => 'Rules',
        'rules_body' => 'Classification level, accessor profile and context map to an action. An explicit deny wins at any priority; with no matching rule the request is denied.',
        'runtime_title' => 'Runtime',
        'runtime_body' => 'Just-in-time grants carry a clock, an approver and, for break-the-glass, a privacy review. Every decision is append-only.',
    ],

    'stats' => [
        'resources' => 'Protected resources',
        'stale' => ':count stale classification(s)',
        'active_grants' => 'Active grants',
        'grants_description' => 'Just-in-time access in force',
        'break_glass' => 'Break-the-glass (7 days)',
        'overdue_reviews' => 'Overdue privacy reviews',
        'unowned' => ':count unowned resource(s)',
    ],

    'exposure' => [
        'heading' => 'Exposure in the current window',
        'description' => 'Weight of sensitive data consumed per subject. Derived from decisions, not stored separately.',
        'subject' => 'Subject',
        'decisions' => 'Decisions',
        'weight' => 'Weight',
        'empty' => 'No decisions in this window.',
    ],

    'links' => [
        'resources' => 'Protected resources',
        'policies' => 'Access policies',
        'grants' => 'Access grants',
        'decisions' => 'Access decisions',
    ],

    'decision' => [
        'unauthenticated' => 'Unauthenticated.',
        'session_not_allowed' => 'This endpoint requires a scoped API token, not a browser session.',
        'missing_scope' => 'The token is missing the access.decide scope.',
        'no_client' => 'The token is not bound to a client.',
        'idempotency_key' => 'The Idempotency-Key header must be 1 to 128 printable characters.',
        'idempotency_conflict' => 'This Idempotency-Key was already used with a different request.',
    ],

];
