<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 0.2 : la fiche devient un vrai dossier d'accompagnement.
 *
 *  sf_familles  + commune, territoire, orientation (qui a orienté la famille), besoins (liste séparée
 *                 par « | »), referent (compte Nextcloud), consentement (date), date_fin, motif_fin
 *  sf_membres   + etablissement (école, emploi…), vigilance (allergies, handicap, traitement…)
 *  sf_suivis    + type (entretien, visite…) et duree (minutes) : une note devient une « intervention »
 *  sf_actions   NOUVEAU : à faire et rendez-vous (échéance, heure, responsable, fait / pas fait)
 *  sf_objectifs NOUVEAU : objectifs de l'accompagnement (en cours / atteint / abandonné)
 *
 * On n'enlève rien : les données de la 0.1 restent telles quelles.
 *
 * Migration « incrémentale » : elle passe APRÈS Version000100 (numéro plus grand) et part
 * des tables existantes. Toutes les nouvelles colonnes acceptent NULL (notnull false) :
 * les familles déjà saisies n'ont pas de valeur pour elles, et c'est normal.
 */
class Version000200Date20261108 extends SimpleMigrationStep {
    /** Même principe que Version000100 : on décrit le schéma, Nextcloud exécute (ALTER TABLE, CREATE TABLE). */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Petite fonction locale (closure) pour ajouter une colonne SEULEMENT si elle manque (hasColumn) :
        // la migration reste relançable sans erreur « colonne déjà existante ».
        // `use ($schema)` : la closure a besoin de $schema, défini à l'extérieur.
        // `: void` : elle ne renvoie rien.
        $ajouter = function (string $table, string $col, string $type, array $opts) use ($schema): void {
            $t = $schema->getTable($table);
            if (!$t->hasColumn($col)) { $t->addColumn($col, $type, $opts); }
        };

        // Nouvelles colonnes de la fiche famille.
        $ajouter('sf_familles', 'commune', Types::STRING, ['notnull' => false, 'length' => 120]);
        $ajouter('sf_familles', 'territoire', Types::STRING, ['notnull' => false, 'length' => 120]);
        $ajouter('sf_familles', 'orientation', Types::STRING, ['notnull' => false, 'length' => 120]);
        $ajouter('sf_familles', 'besoins', Types::STRING, ['notnull' => false, 'length' => 1000]);
        $ajouter('sf_familles', 'referent', Types::STRING, ['notnull' => false, 'length' => 64]);
        $ajouter('sf_familles', 'consentement', Types::STRING, ['notnull' => false, 'length' => 10]);
        $ajouter('sf_familles', 'date_fin', Types::STRING, ['notnull' => false, 'length' => 10]);
        $ajouter('sf_familles', 'motif_fin', Types::STRING, ['notnull' => false, 'length' => 255]);

        // Nouvelles colonnes des membres.
        $ajouter('sf_membres', 'etablissement', Types::STRING, ['notnull' => false, 'length' => 160]);
        $ajouter('sf_membres', 'vigilance', Types::TEXT, ['notnull' => false]);

        // Nouvelles colonnes des notes de suivi.
        $ajouter('sf_suivis', 'type', Types::STRING, ['notnull' => false, 'length' => 80]);
        $ajouter('sf_suivis', 'duree', Types::INTEGER, ['notnull' => false]);

        // --- sf_actions (nouvelle table, créée seulement si absente : hasTable) ---
        if (!$schema->hasTable('sf_actions')) {
            $t = $schema->createTable('sf_actions');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('famille_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('titre', Types::STRING, ['notnull' => true, 'length' => 255]);
            $t->addColumn('genre', Types::STRING, ['notnull' => true, 'length' => 10, 'default' => 'action']);   // action | rdv
            $t->addColumn('echeance', Types::STRING, ['notnull' => false, 'length' => 10]);
            // heure 'HH:MM' : 5 caractères.
            $t->addColumn('heure', Types::STRING, ['notnull' => false, 'length' => 5]);
            $t->addColumn('responsable', Types::STRING, ['notnull' => false, 'length' => 64]);
            $t->addColumn('fait', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            // fait_le : horodatage Unix du moment où l'action a été cochée.
            $t->addColumn('fait_le', Types::BIGINT, ['notnull' => false]);
            $t->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['famille_id'], 'sf_actions_fam');
            // Accélère « mes actions » sur l'écran d'accueil.
            $t->addIndex(['responsable'], 'sf_actions_resp');
        }

        // --- sf_objectifs (nouvelle table) ---
        if (!$schema->hasTable('sf_objectifs')) {
            $t = $schema->createTable('sf_objectifs');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('famille_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('titre', Types::STRING, ['notnull' => true, 'length' => 255]);
            $t->addColumn('statut', Types::STRING, ['notnull' => true, 'length' => 12, 'default' => 'en_cours']);   // en_cours | atteint | abandonne
            $t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['famille_id'], 'sf_objectifs_fam');
        }

        return $schema;
    }
}
