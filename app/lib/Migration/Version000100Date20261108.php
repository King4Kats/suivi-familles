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
 * Création des 5 tables de l'app (Nextcloud ajoute devant le préfixe « oc_ ») :
 *  - sf_familles : une ligne par famille (coordonnées, situation, statut actif / clos)
 *  - sf_membres  : les personnes de la famille (lien, naissance, médecin traitant, psychologue…)
 *  - sf_acces    : QUI peut voir QUELLE famille (+ sa fonction, + droit de modifier la fiche).
 *                  C'est cette table qui fait le cloisonnement : pas de ligne = pas d'accès.
 *  - sf_suivis   : notes de suivi datées ; « prive » = visible seulement par l'auteur et la direction
 *  - sf_journal  : trace de chaque consultation et modification (qui, quoi, quand)
 *
 * Dates « métier » (naissance, entrée, date du suivi) en texte 'AAAA-MM-JJ' (triable, sans fuseau) ;
 * horodatages techniques (created_at…) en secondes Unix.
 * Booléens : notnull=false obligatoire (exigence Nextcloud pour la compatibilité Oracle).
 *
 * QU'EST-CE QU'UNE MIGRATION ? Un fichier qui décrit un changement de structure de la base.
 * Nextcloud l'exécute UNE fois, à l'installation ou à la mise à jour de l'app, et retient
 * qu'elle est passée. Le nom de la classe (Version + n° de version + date) fixe l'ordre d'exécution.
 * On ne modifie jamais une migration déjà déployée : on en ajoute une nouvelle (voir Version000200).
 */
class Version000100Date20261108 extends SimpleMigrationStep {
    /**
     * Décrit le schéma voulu. On ne lance pas de SQL : on modifie un objet ISchemaWrapper
     * (la description des tables) et on le renvoie ; Nextcloud calcule et exécute lui-même
     * les CREATE TABLE adaptés à la base (MySQL, PostgreSQL, SQLite…).
     * Closure $schemaClosure : une fonction qui, appelée, donne le schéma actuel.
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Chaque table est entourée de « if (!hasTable) » : la migration est RELANÇABLE sans erreur.
        // Si la table existe déjà (relance, installation partielle), on n'y touche pas.

        // --- sf_familles ---
        if (!$schema->hasTable('sf_familles')) {
            $t = $schema->createTable('sf_familles');
            // Clé primaire auto-incrémentée : la base attribue 1, 2, 3… à chaque nouvelle ligne.
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('nom', Types::STRING, ['notnull' => true, 'length' => 120]);
            $t->addColumn('adresse', Types::STRING, ['notnull' => false, 'length' => 255]);
            $t->addColumn('telephone', Types::STRING, ['notnull' => false, 'length' => 40]);
            $t->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 160]);
            // TEXT : texte long sans limite fixe (STRING exige une longueur maximale).
            $t->addColumn('situation', Types::TEXT, ['notnull' => false]);
            $t->addColumn('date_entree', Types::STRING, ['notnull' => false, 'length' => 10]);
            $t->addColumn('statut', Types::STRING, ['notnull' => true, 'length' => 10, 'default' => 'actif']);
            // created_by : uid Nextcloud (64 caractères max, comme dans Nextcloud).
            $t->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
        }

        // --- sf_membres ---
        if (!$schema->hasTable('sf_membres')) {
            $t = $schema->createTable('sf_membres');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            // famille_id : la famille à laquelle appartient le membre.
            $t->addColumn('famille_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('prenom', Types::STRING, ['notnull' => true, 'length' => 80]);
            $t->addColumn('nom', Types::STRING, ['notnull' => false, 'length' => 80]);
            $t->addColumn('lien', Types::STRING, ['notnull' => false, 'length' => 40]);
            $t->addColumn('date_naissance', Types::STRING, ['notnull' => false, 'length' => 10]);
            $t->addColumn('telephone', Types::STRING, ['notnull' => false, 'length' => 40]);
            $t->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 160]);
            $t->addColumn('medecin_traitant', Types::STRING, ['notnull' => false, 'length' => 160]);
            $t->addColumn('psychologue', Types::STRING, ['notnull' => false, 'length' => 160]);
            $t->addColumn('autres_suivis', Types::TEXT, ['notnull' => false]);
            $t->addColumn('remarques', Types::TEXT, ['notnull' => false]);
            $t->addColumn('ordre', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            // Index : accélère « tous les membres de la famille X » (comme l'index d'un livre).
            $t->addIndex(['famille_id'], 'sf_membres_fam');
        }

        // --- sf_acces : le cloisonnement ---
        if (!$schema->hasTable('sf_acces')) {
            $t = $schema->createTable('sf_acces');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('famille_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('fonction', Types::STRING, ['notnull' => false, 'length' => 80]);
            $t->addColumn('peut_modifier', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            $t->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            // Index UNIQUE : la base refuse deux lignes pour le même couple (famille, personne).
            $t->addUniqueIndex(['famille_id', 'user_id'], 'sf_acces_fam_user');
            // Accélère « toutes les familles de telle personne » (appelé à chaque requête).
            $t->addIndex(['user_id'], 'sf_acces_user');
        }

        // --- sf_suivis ---
        if (!$schema->hasTable('sf_suivis')) {
            $t = $schema->createTable('sf_suivis');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $t->addColumn('famille_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            // membre_id facultatif (notnull false) : une note peut concerner toute la famille.
            $t->addColumn('membre_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
            $t->addColumn('auteur', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('date_suivi', Types::STRING, ['notnull' => true, 'length' => 10]);
            $t->addColumn('texte', Types::TEXT, ['notnull' => false]);
            $t->addColumn('prive', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            $t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['famille_id'], 'sf_suivis_fam');
        }

        // --- sf_journal ---
        if (!$schema->hasTable('sf_journal')) {
            $t = $schema->createTable('sf_journal');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            // famille_id facultatif, et SANS lien forcé vers sf_familles : la trace survit à la suppression de la famille.
            $t->addColumn('famille_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
            $t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $t->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 32]);
            $t->addColumn('detail', Types::STRING, ['notnull' => false, 'length' => 255]);
            $t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            // Index taillés pour les deux lectures du journal :
            // « l'historique d'une famille par date » et « dernière consultation de X sur Y » (Store::derniereAction).
            $t->addIndex(['famille_id', 'created_at'], 'sf_journal_fam');
            $t->addIndex(['user_id', 'famille_id', 'action'], 'sf_journal_user');
        }

        // On renvoie le schéma modifié : Nextcloud applique les différences.
        return $schema;
    }
}
