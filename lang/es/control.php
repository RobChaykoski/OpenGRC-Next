<?php

return [
    'navigation' => [
        'label' => 'Controles',
        'group' => 'Fundamentos',
    ],
    'model' => [
        'label' => 'Control',
        'plural_label' => 'Controles',
    ],
    'breadcrumb' => [
        'title' => 'Controles',
    ],
    'form' => [
        'code' => [
            'tooltip' => 'Ingrese un código único para este control. Este código se utilizará para identificar este control en el sistema.',
        ],
        'standard' => [
            'label' => 'Estándar',
            'tooltip' => 'Todos los controles deben pertenecer a un estándar. Si no tiene un estándar para relacionar este control, considere crear uno primero.',
        ],
        'enforcement' => [
            'tooltip' => 'Seleccione una categoría de cumplimiento para este control. Esto ayudará a determinar cómo se aplica este control.',
        ],
        'type' => [
            'label' => 'Tipo',
            'tooltip' => 'Seleccione el tipo de control (ej: Preventivo, Detectivo, Correctivo). Esto categoriza el control según cuándo actúa en relación con un incidente de seguridad.',
        ],
        'category' => [
            'label' => 'Categoría',
            'tooltip' => 'Seleccione la categoría del control (ej: Técnico, Administrativo, Físico). Esto categoriza el control según su enfoque de implementación.',
        ],
        'title' => [
            'tooltip' => 'Ingrese un título para este control.',
        ],
        'description' => [
            'tooltip' => 'Ingrese una descripción para este control. Esto debe describir, en detalle, los requisitos para este control.',
        ],
        'discussion' => [
            'tooltip' => 'Opcional: Proporcione cualquier contexto o información adicional sobre este control que ayude a alguien a determinar cómo implementarlo.',
        ],
        'canonical_outcomes' => [
            'label' => 'Resultados canónicos',
            'helper' => '¿Qué resultados satisface este control? Los vínculos los revisa una persona y nunca se emparejan automáticamente. Déjelo vacío si el requisito no sirve claramente a un resultado: un control sin vínculo se informa como una brecha de cobertura, no como un fallo.',
            'policy_helper' => '¿Qué resultados apoya esta política o documento? Los vínculos los revisa una persona y nunca se emparejan automáticamente. Una política puede apoyar un resultado directamente o mediante los controles a los que está vinculada. Dejarlo vacío es una brecha de cobertura que revisar, no un fallo.',
        ],
        'test' => [
            'label' => 'Plan de Prueba',
            'tooltip' => 'Opcional: ¿Cómo planea probar que este control está implementado y es efectivo?',
        ],
    ],
    'table' => [
        'description' => 'Los controles son el \'cómo\' de la implementación de la seguridad: son los mecanismos, políticas, procedimientos y herramientas específicas utilizadas para hacer cumplir los estándares y proteger los activos. Los controles pueden ser técnicos (como firewalls o cifrado), administrativos (como políticas o capacitación), o físicos (como cámaras de seguridad o cerraduras de puertas). Cada control debe estar diseñado para abordar riesgos específicos y cumplir con requisitos de seguridad particulares definidos por los estándares. Por ejemplo, para cumplir con un estándar que requiere transmisión segura de datos, un control podría especificar el uso de TLS 1.2 o superior para todas las comunicaciones externas. Los controles son la manifestación práctica de los estándares de seguridad y forman la columna vertebral de la infraestructura de seguridad de una organización.',
        'empty_state' => [
            'heading' => 'No se encontraron controles',
            'description' => 'Comience importando un paquete de controles o creando un nuevo control.',
        ],
        'columns' => [
            'code' => 'Código del Control',
            'title' => 'Título del Control',
            'canonical_outcomes' => 'Resultado canónico',
            'canonical_outcomes_unlinked' => 'Sin vínculo',
            'type' => 'Tipo de Control',
            'category' => 'Categoría del Control',
            'enforcement' => 'Nivel de Cumplimiento',
            'effectiveness' => 'Efectividad',
            'applicability' => 'Aplicabilidad',
            'assessed' => 'Última Evaluación',
            'created_at' => 'Creado El',
            'updated_at' => 'Actualizado El',
        ],
        'filters' => [
            'canonical_link' => 'Vínculo con resultado canónico',
            'canonical_linked' => 'Vinculado',
            'canonical_unlinked' => 'Sin vínculo',
            'type' => 'Tipo de Control',
            'category' => 'Categoría del Control',
            'enforcement' => 'Nivel de Cumplimiento',
        ],
    ],
    'infolist' => [
        'section_title' => 'Detalles del Control',
        'canonical_outcomes' => 'Resultados canónicos',
        'canonical_outcomes_empty' => 'Sin vínculo: no hay una coincidencia revisada. Es una brecha de cobertura, no un fallo.',
        'canonical_outcomes_policy_empty' => 'Sin vínculo: no hay una coincidencia revisada. Esta política es una brecha de cobertura hasta que alguien la revise, no un fallo.',
        'test_plan' => 'Plan de Prueba',
    ],
];
