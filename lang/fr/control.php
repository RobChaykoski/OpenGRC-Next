<?php

return [
    'navigation' => [
        'label' => 'Contrôles',
        'group' => 'Fondations',
    ],
    'model' => [
        'label' => 'Contrôle',
        'plural_label' => 'Contrôles',
    ],
    'breadcrumb' => [
        'title' => 'Contrôles',
    ],
    'form' => [
        'code' => [
            'tooltip' => 'Entrez un code unique pour ce contrôle. Ce code sera utilisé pour identifier ce contrôle dans le système.',
        ],
        'standard' => [
            'label' => 'Norme',
            'tooltip' => 'Tous les contrôles doivent appartenir à une norme. Si vous n\'avez pas de norme à laquelle rattacher ce contrôle, envisagez d\'en créer une d\'abord.',
        ],
        'enforcement' => [
            'tooltip' => 'Sélectionnez une catégorie d\'application pour ce contrôle. Cela aidera à déterminer comment ce contrôle est appliqué.',
        ],
        'type' => [
            'label' => 'Type',
            'tooltip' => 'Sélectionnez le type de contrôle (ex: Préventif, Détectif, Correctif). Cela catégorise le contrôle en fonction du moment où il agit par rapport à un incident de sécurité.',
        ],
        'category' => [
            'label' => 'Catégorie',
            'tooltip' => 'Sélectionnez la catégorie du contrôle (ex: Technique, Administratif, Physique). Cela catégorise le contrôle en fonction de son approche de mise en œuvre.',
        ],
        'title' => [
            'tooltip' => 'Entrez un titre pour ce contrôle.',
        ],
        'description' => [
            'tooltip' => 'Entrez une description pour ce contrôle. Cela doit décrire, en détail, les exigences pour ce contrôle.',
        ],
        'discussion' => [
            'tooltip' => 'Optionnel : Fournissez tout contexte ou information supplémentaire sur ce contrôle qui aiderait quelqu\'un à déterminer comment le mettre en œuvre.',
        ],
        'canonical_outcomes' => [
            'label' => 'Résultats canoniques',
            'helper' => 'Quels résultats ce contrôle satisfait-il ? Les liens sont revus par une personne et ne sont jamais établis automatiquement. Laissez vide si l\'exigence ne sert pas clairement un résultat : un contrôle non lié est signalé comme un écart de couverture, pas comme un échec.',
            'policy_helper' => 'Quels résultats cette politique ou ce document soutient-il ? Les liens sont revus par une personne et ne sont jamais établis automatiquement. Une politique peut soutenir un résultat directement ou par les contrôles auxquels elle est rattachée. Laisser vide est un écart de couverture à revoir, pas un échec.',
        ],
        'test' => [
            'label' => 'Plan de Test',
            'tooltip' => 'Optionnel : Comment prévoyez-vous de tester que ce contrôle est en place et efficace ?',
        ],
    ],
    'table' => [
        'description' => 'Les contrôles représentent le \'comment\' de la mise en œuvre de la sécurité - ce sont les mécanismes, politiques, procédures et outils spécifiques utilisés pour appliquer les normes et protéger les actifs. Les contrôles peuvent être techniques (comme les pare-feu ou le chiffrement), administratifs (comme les politiques ou la formation), ou physiques (comme les caméras de sécurité ou les serrures de porte). Chaque contrôle doit être conçu pour répondre à des risques spécifiques et satisfaire aux exigences de sécurité particulières définies par les normes. Par exemple, pour répondre à une norme exigeant une transmission sécurisée des données, un contrôle pourrait spécifier l\'utilisation de TLS 1.2 ou supérieur pour toutes les communications externes. Les contrôles sont la manifestation pratique des normes de sécurité et constituent l\'épine dorsale de l\'infrastructure de sécurité d\'une organisation.',
        'empty_state' => [
            'heading' => 'Aucun contrôle trouvé',
            'description' => 'Commencez par importer un ensemble de standards ou créer un nouveau contrôle.',
        ],
        'columns' => [
            'code' => 'Code du Contrôle',
            'title' => 'Titre du Contrôle',
            'standard' => 'Standard',
            'canonical_outcomes' => 'Résultat canonique',
            'canonical_outcomes_unlinked' => 'Non lié',
            'type' => 'Type de Contrôle',
            'category' => 'Catégorie du Contrôle',
            'enforcement' => 'Niveau d\'Application',
            'effectiveness' => 'Efficacité',
            'applicability' => 'Applicabilité',
            'assessed' => 'Dernière Évaluation',
            'created_at' => 'Créé Le',
            'updated_at' => 'Mis à Jour Le',
        ],
        'filters' => [
            'standard' => 'Standard',
            'canonical_link' => 'Lien vers un résultat canonique',
            'canonical_linked' => 'Lié',
            'canonical_unlinked' => 'Non lié',
            'effectiveness' => 'Efficacité',
            'type' => 'Type',
            'category' => 'Catégorie',
            'enforcement' => 'Application',
            'applicability' => 'Applicabilité',
        ],
    ],
    'infolist' => [
        'section_title' => 'Détails du Contrôle',
        'canonical_outcomes' => 'Résultats canoniques',
        'canonical_outcomes_empty' => 'Non lié — aucune correspondance revue. C\'est un écart de couverture, pas un échec.',
        'canonical_outcomes_policy_empty' => 'Non lié — aucune correspondance revue. Cette politique est un écart de couverture jusqu\'à sa revue, pas un échec.',
        'test_plan' => 'Plan de Test',
    ],
];
