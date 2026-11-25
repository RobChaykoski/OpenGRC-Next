<?php

return [
    'check' => [
        'evidence' => 'Registro de evidencia',
        'audit' => 'Registro de auditoría',
    ],

    'summary' => [
        'not_installed' => ':check aún no está instalado; ejecute las migraciones.',
        'unreadable' => 'No se pudo leer :check; consulte el registro de la aplicación.',
        'evidence_passed' => 'Se verificaron :count registro(s) de evidencia; la cadena está intacta.',
        'evidence_warned' => 'Se verificaron :count registro(s) de evidencia; :warnings advertencia(s).',
        'evidence_failed' => 'Se verificaron :count registro(s) de evidencia; :problems problema(s) encontrados.',
        'audit_passed' => 'Registro de auditoría intacto en :total entradas.',
        'audit_pruned' => 'Se depuraron :missing fila(s) de actividad reflejada(s); las entradas del registro permanecen.',
        'audit_broken' => 'La cadena del registro de auditoría está rota en la entrada n.º :entry.',
        'audit_altered' => ':count fila(s) de actividad ya no coinciden con el registro de auditoría.',
    ],

    'command' => [
        'unknown_check' => 'Verificación desconocida [:check]. Use evidence, audit o all.',
        'pass' => 'CORRECTO',
        'warn' => 'ADVERTENCIA',
        'fail' => 'FALLO',
        'detail' => 'Verificado a las :time en :duration ms.',
    ],

    'widget' => [
        'title' => 'Integridad de los registros',
        'value' => [
            'pass' => 'Intacta',
            'warn' => 'Atención',
            'fail' => 'Fallida',
            'never' => 'Sin ejecutar',
        ],
        'description' => [
            'never' => 'Ejecute php artisan opengrc:integrity:verify',
            'last_run' => 'Cadenas de evidencia y auditoría; última verificación :time',
            'stale' => 'Última verificación :time; es posible que la programación se haya detenido',
            'issue' => ':summary',
        ],
    ],
];
