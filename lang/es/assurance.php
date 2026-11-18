<?php

return [
    'navigation' => [
        'label' => 'Matriz de aseguramiento',
        'group' => 'Fundamentos',
    ],
    'page' => [
        'title' => 'Matriz de aseguramiento',
    ],
    'framework' => [
        'label' => 'Marco',
        'help' => 'Elija un marco para ver los resultados que exige y qué los respalda actualmente. Esta página solo informa de vínculos revisados y evidencia; no es una puntuación de cumplimiento. Los resultados sin un control vinculado se muestran como trabajo pendiente de revisión, no como fallos.',
        'none' => 'No hay marcos en el catálogo. Ejecute php artisan opengrc:catalog:sync.',
    ],
    'columns' => [
        'code' => 'Código',
        'outcome' => 'Resultado',
        'category' => 'Categoría',
        'priority' => 'Prioridad',
        'automation' => 'Automatización',
        'controls' => 'Controles',
        'policies' => 'Políticas',
        'evidence' => 'Evidencia',
        'status' => 'Estado',
    ],
    'priority' => [
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja',
    ],
    'automation' => [
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja',
    ],
    'status' => [
        'covered' => 'Vinculado + con evidencia',
        'no_evidence' => 'Falta evidencia',
        'no_control' => 'Sin control revisado',
    ],
    'filters' => [
        'gaps' => 'Solo brechas',
        'only_gaps' => 'Solo resultados que requieren revisión',
        'only_gaps_helper' => 'Muestra resultados sin control vinculado, o con control vinculado pero sin evidencia.',
    ],
    'score' => [
        'heading' => 'Puntuación de aseguramiento',
        'total' => 'Puntuación',
        'counts' => ':reviewed de :outcomes resultados revisados',
        'trend' => ':change en :days días',
        'no_trend' => 'Aún no hay tendencia: la instantánea diaria la construye.',
        'weight' => ':weight% de peso',
        'help' => 'Una vista ponderada de los vínculos revisados y la calidad de la evidencia. No es un porcentaje de cumplimiento; lea los componentes antes de actuar.',
        'unavailable' => 'Aún no hay puntuación: este marco no tiene resultados en el catálogo.',
        'components' => [
            'reviewed' => 'Revisado',
            'evidence' => 'Evidencia',
            'quality' => 'Calidad',
            'automation' => 'Automatización',
            'monitoring' => 'Monitoreo',
        ],
    ],
    'not_set' => 'Sin definir',
    'latest_evidence' => 'Última evidencia: :date',
    'empty' => [
        'heading' => 'No hay resultados para este marco',
        'description' => 'Importe el marco o amplíe config/framework_scoping.php y ejecute opengrc:catalog:sync.',
    ],
];
