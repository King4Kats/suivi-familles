<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

// TABLE DE ROUTAGE : chaque URL → une méthode de contrôleur ('controleur#methode').
// Toutes les routes « /api/... » répondent en JSON. Le navigateur ne décide de RIEN :
// à chaque appel, le serveur revérifie qui demande et à quelle famille il a droit (FamillesService).
//
// Lire une route :
//   'name' => 'api#fiche'  → classe ApiController (préfixe « api » + Controller), méthode fiche().
//                            'page#index' → PageController::index().
//   'url'  => '/api/familles/{id}' → adresse relative à l'app (…/apps/suivi_familles/api/familles/12).
//                            {id} est une variable : Nextcloud la passe au paramètre $id de la méthode.
//   'verb' => méthode HTTP : GET lire, POST créer, PUT modifier, DELETE supprimer.
//            La même URL peut donc mener à plusieurs méthodes selon le verbe (voir /api/familles/{id}).
// Ce fichier RENVOIE (return) un simple tableau PHP, que Nextcloud lit au chargement de l'app.
return [
    'routes' => [
        // La page (le HTML qui charge js/main.js).
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // --- Synthèses ---
        ['name' => 'api#accueil', 'url' => '/api/accueil', 'verb' => 'GET'],                    // ma journée
        ['name' => 'api#reglages', 'url' => '/api/reglages', 'verb' => 'GET'],                  // listes de choix (fixes)

        // --- Familles ---
        ['name' => 'api#familles', 'url' => '/api/familles', 'verb' => 'GET'],                  // celles que je peux voir
        ['name' => 'api#familleCreer', 'url' => '/api/familles', 'verb' => 'POST'],             // direction
        ['name' => 'api#fiche', 'url' => '/api/familles/{id}', 'verb' => 'GET'],                // fiche complète (journalisée)
        ['name' => 'api#familleModifier', 'url' => '/api/familles/{id}', 'verb' => 'PUT'],      // direction ou intervenant autorisé
        ['name' => 'api#familleSupprimer', 'url' => '/api/familles/{id}', 'verb' => 'DELETE'],  // direction
        ['name' => 'api#statut', 'url' => '/api/familles/{id}/statut', 'verb' => 'PUT'],        // clore / rouvrir (direction)
        ['name' => 'api#referent', 'url' => '/api/familles/{id}/referent', 'verb' => 'PUT'],    // direction
        ['name' => 'api#impression', 'url' => '/api/familles/{id}/impression', 'verb' => 'POST'], // trace au journal

        // --- Membres d'une famille ---
        ['name' => 'api#membreCreer', 'url' => '/api/familles/{id}/membres', 'verb' => 'POST'],
        ['name' => 'api#membreModifier', 'url' => '/api/membres/{id}', 'verb' => 'PUT'],
        ['name' => 'api#membreSupprimer', 'url' => '/api/membres/{id}', 'verb' => 'DELETE'],

        // --- Notes de suivi (interventions) ---
        ['name' => 'api#suiviCreer', 'url' => '/api/familles/{id}/suivis', 'verb' => 'POST'],
        ['name' => 'api#suiviModifier', 'url' => '/api/suivis/{id}', 'verb' => 'PUT'],          // auteur ou direction
        ['name' => 'api#suiviSupprimer', 'url' => '/api/suivis/{id}', 'verb' => 'DELETE'],

        // --- Actions à faire et rendez-vous (toute l'équipe) ---
        ['name' => 'api#actionCreer', 'url' => '/api/familles/{id}/actions', 'verb' => 'POST'],
        ['name' => 'api#actionModifier', 'url' => '/api/actions/{id}', 'verb' => 'PUT'],
        ['name' => 'api#actionSupprimer', 'url' => '/api/actions/{id}', 'verb' => 'DELETE'],   // créateur ou direction

        // --- Objectifs (mêmes droits que la fiche) ---
        ['name' => 'api#objectifCreer', 'url' => '/api/familles/{id}/objectifs', 'verb' => 'POST'],
        ['name' => 'api#objectifModifier', 'url' => '/api/objectifs/{id}', 'verb' => 'PUT'],
        ['name' => 'api#objectifSupprimer', 'url' => '/api/objectifs/{id}', 'verb' => 'DELETE'],

        // --- Équipe d'une famille (qui y a accès) : direction seulement ---
        ['name' => 'api#accesAjouter', 'url' => '/api/familles/{id}/acces', 'verb' => 'POST'],
        ['name' => 'api#accesModifier', 'url' => '/api/acces/{id}', 'verb' => 'PUT'],
        ['name' => 'api#accesRetirer', 'url' => '/api/acces/{id}', 'verb' => 'DELETE'],
        ['name' => 'api#utilisateurs', 'url' => '/api/utilisateurs', 'verb' => 'GET'],          // recherche de comptes

        // --- Journal (direction) ---
        ['name' => 'api#journal', 'url' => '/api/journal', 'verb' => 'GET'],                    // ?famille=12 pour une seule
    ],
];
