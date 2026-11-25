<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported inference runtimes (ADR 0059)
    |--------------------------------------------------------------------------
    |
    | Map a runtime identifier (lowercase) to the version range the institution
    | supports. An artifact that records a runtime not listed here, or a version
    | outside its range, raises the "unsupported inference runtime" finding.
    |
    | The version must be numeric and optionally dotted, or a llama.cpp build
    | number such as b3600. A version that cannot be compared is treated as
    | unverified, not as supported.
    |
    | Leaving the map empty is fail-closed: no runtime is approved, and any
    | recorded runtime becomes a finding until the baseline is set here.
    |
    | Examples:
    |
    |   'vllm' => ['min' => '0.6.0'],
    |   'llama.cpp' => ['min' => '3600'],
    |   'onnxruntime' => ['min' => '1.18.0', 'max' => '1.22.0'],
    |
    */
    'runtime_baseline' => [
        //
    ],
];
