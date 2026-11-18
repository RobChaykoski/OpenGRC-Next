<?php

return [
    'navigation' => [
        'label' => 'Matrice d\'assurance',
        'group' => 'Fondations',
    ],
    'page' => [
        'title' => 'Matrice d\'assurance',
    ],
    'framework' => [
        'label' => 'Référentiel',
        'help' => 'Choisissez un référentiel pour voir les résultats qu\'il exige et ce qui les soutient actuellement. Cette page ne présente que des liens revus et des preuves ; ce n\'est pas un score de conformité. Les résultats sans contrôle lié sont affichés comme du travail à revoir, pas comme des échecs.',
        'none' => 'Aucun référentiel dans le catalogue. Exécutez php artisan opengrc:catalog:sync.',
    ],
    'columns' => [
        'code' => 'Code',
        'outcome' => 'Résultat',
        'category' => 'Catégorie',
        'priority' => 'Priorité',
        'automation' => 'Automatisation',
        'controls' => 'Contrôles',
        'policies' => 'Politiques',
        'evidence' => 'Preuves',
        'status' => 'État',
    ],
    'priority' => [
        'high' => 'Haute',
        'medium' => 'Moyenne',
        'low' => 'Basse',
    ],
    'automation' => [
        'high' => 'Élevée',
        'medium' => 'Moyenne',
        'low' => 'Faible',
    ],
    'status' => [
        'covered' => 'Lié + preuve',
        'no_evidence' => 'Preuve manquante',
        'no_control' => 'Aucun contrôle revu',
    ],
    'filters' => [
        'gaps' => 'Écarts uniquement',
        'only_gaps' => 'Seulement les résultats à revoir',
        'only_gaps_helper' => 'Affiche les résultats sans contrôle lié, ou avec un contrôle lié mais sans preuve.',
    ],
    'not_set' => 'Non défini',
    'latest_evidence' => 'Dernière preuve : :date',
    'empty' => [
        'heading' => 'Aucun résultat pour ce référentiel',
        'description' => 'Importez le référentiel ou étendez config/framework_scoping.php, puis exécutez opengrc:catalog:sync.',
    ],
];
