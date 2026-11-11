<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Migration;

use Closure;
use OCA\SuiviFamilles\Service\Store;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 0.3 : CHIFFREMENT des champs sensibles (liste dans Store::CHIFFRES).
 *
 * Deux temps, comme le prévoit Nextcloud :
 *  1. changeSchema() : une valeur chiffrée est plus longue que le texte d'origine (environ 2 à 3 fois
 *     plus). Les colonnes « médecin traitant » et « psychologue » (160 caractères) passent donc en
 *     texte long, comme les autres champs chiffrés.
 *  2. postSchemaChange() : chiffre les valeurs DÉJÀ enregistrées. Une valeur qui porte déjà la marque
 *     « sfc1: » est laissée telle quelle : la migration peut être relancée sans danger.
 */
class Version000300Date20261111 extends SimpleMigrationStep {
    // Injection de dépendances : Nextcloud fournit la connexion et notre Store (qui sait chiffrer).
    public function __construct(private IDBConnection $db, private Store $store) {
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $table = $schema->getTable('sf_membres');
        $change = false;
        foreach (['medecin_traitant', 'psychologue'] as $col) {
            // On ne touche à la colonne que si elle n'est pas déjà en texte long.
            // (instanceof TextType : test valable quelle que soit la version de la bibliothèque Doctrine.)
            $colonne = $table->getColumn($col);
            if (!($colonne->getType() instanceof \Doctrine\DBAL\Types\TextType)) {
                $colonne->setType(\Doctrine\DBAL\Types\Type::getType(Types::TEXT))->setLength(null)->setNotnull(false);
                $change = true;
            }
        }
        // Renvoyer null = « rien à changer dans le schéma ».
        return $change ? $schema : null;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $total = 0;
        foreach (Store::CHIFFRES as $table => $colonnes) {
            // On lit toutes les lignes, colonnes sensibles seulement (plus l'id pour réécrire).
            $qb = $this->db->getQueryBuilder();
            $qb->select(...array_merge(['id'], $colonnes))->from($table);
            $resultat = $qb->executeQuery();
            while ($ligne = $resultat->fetch()) {
                $maj = [];
                foreach ($colonnes as $col) {
                    $v = $ligne[$col];
                    // Seulement ce qui n'est ni vide ni déjà chiffré.
                    if (is_string($v) && $v !== '' && !str_starts_with($v, Store::MARQUE)) {
                        $maj[$col] = $this->store->chiffrer($v);
                    }
                }
                if ($maj === []) { continue; }
                $u = $this->db->getQueryBuilder();
                $u->update($table);
                foreach ($maj as $col => $v) { $u->set($col, $u->createNamedParameter($v)); }
                $u->where($u->expr()->eq('id', $u->createNamedParameter((int)$ligne['id'], IQueryBuilder::PARAM_INT)))->executeStatement();
                $total++;
            }
            $resultat->closeCursor();
        }
        $output->info("Familles : $total ligne(s) chiffrée(s)");
    }
}
