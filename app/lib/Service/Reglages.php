<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Service;

use OCA\SuiviFamilles\AppInfo\Application;
use OCP\IAppConfig;

/**
 * LES LISTES DE CHOIX proposées dans les formulaires (types d'intervention, besoins, territoires…).
 *
 * Les listes par défaut sont ci-dessous. Chaque structure peut remplacer une ou plusieurs listes
 * par une commande, sans toucher au code (les listes non données gardent leur valeur par défaut) :
 *
 *   occ config:app:set suivi_familles listes --value='{"territoires":["Nord","Sud","Hors territoire"]}'
 *
 * Changer une liste ne modifie pas ce qui est déjà enregistré dans les fiches.
 *
 * Ce sont des SUGGESTIONS pour l'écran : le serveur n'oblige pas à choisir dans ces listes
 * (FamillesService vérifie seulement la longueur des textes).
 */
class Reglages {
    // `public const` : constante de classe, un tableau figé qui ne change pas pendant l'exécution.
    public const DEFAUTS = [
        // La forme de chaque intervention (onglet Suivi).
        'types' => ['Entretien', 'Visite à domicile', 'Appel téléphonique', 'Atelier collectif', 'Accompagnement à une démarche', 'Orientation vers un partenaire', 'Réunion de coordination', 'Autre'],
        // Les thèmes sur lesquels la famille est accompagnée (plusieurs possibles).
        'besoins' => ['Santé mentale, bien-être', 'Alimentation', 'Activité physique', 'Sommeil', 'Parentalité', 'Addictions', 'Accès aux droits et aux soins', 'Isolement, lien social', 'Logement', 'Scolarité'],
        // Où vit la famille.
        // Chaque structure met les siens (voir la commande en tête de fichier).
        'territoires' => ['Territoire 1', 'Territoire 2', 'Hors territoire'],
        // Qui a orienté la famille vers l'association.
        'orientations' => ['Démarche spontanée', 'Médecin, professionnel de santé', 'Établissement scolaire', 'Services sociaux (CCAS, Département)', 'CAF, CPAM', 'Association partenaire', 'Entourage', 'Autre'],
        // Suggestions pour la fonction d'un intervenant (on peut taper autre chose).
        'fonctions' => ['Psychologue', 'Médecin', 'Infirmier·ère', 'Diététicien·ne', 'Éducateur·rice', 'Assistant·e social·e', 'Sophrologue', 'Référent·e', 'Bénévole'],
        // Suggestions pour le lien d'un membre avec la famille.
        'liens' => ['Mère', 'Père', 'Parent', 'Enfant', 'Fille', 'Fils', 'Grand-parent', 'Conjoint·e', 'Tuteur·rice', 'Autre'],
        // Motifs de fin d'accompagnement.
        'motifs' => ['Objectifs atteints', 'Relais pris par un partenaire', 'Déménagement', 'Fin à la demande de la famille', 'Sans nouvelles', 'Autre'],
    ];

    // Injection de dépendances : Nextcloud fournit IAppConfig, le magasin des réglages des applis.
    public function __construct(private IAppConfig $config) {
    }

    /** Toutes les listes, prêtes pour le navigateur.
     *  Qui : tout compte connecté (ces listes ne contiennent aucune donnée personnelle).
     *  `self::` désigne la classe elle-même : self::DEFAUTS = Reglages::DEFAUTS. */
    public function tous(): array {
        // Le réglage « listes » est un texte JSON. json_decode(…, true) le transforme en tableau PHP ;
        // s'il est absent ou mal écrit, on obtient autre chose qu'un tableau et on l'ignore.
        $perso = json_decode($this->config->getValueString(Application::APP_ID, 'listes', '{}'), true);
        $perso = is_array($perso) ? $perso : [];
        $out = [];
        foreach (self::DEFAUTS as $cle => $defaut) {
            $v = $perso[$cle] ?? null;
            // Une liste personnalisée n'est prise que si c'est une vraie liste non vide ;
            // array_map('strval', …) convertit chaque élément en texte, array_values renumérote 0, 1, 2…
            $out[$cle] = is_array($v) && $v !== [] ? array_values(array_map('strval', $v)) : $defaut;
        }
        return $out;
    }
}
