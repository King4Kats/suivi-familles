<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later
/**
 * Lit la base BRUTE (sans passer par l'appli) et vérifie que les champs sensibles y sont chiffrés.
 * Lancé dans le conteneur de test par scripts/tester.sh :
 *   php verifier-chiffrement.php
 * Il passe par Nextcloud pour se connecter à la base (quelle qu'elle soit), mais lit les valeurs
 * telles qu'elles sont stockées : c'est ce que verrait quelqu'un qui aurait volé une copie de la base.
 */
require '/var/www/html/lib/base.php';
\OC_App::loadApp('suivi_familles');

use OCA\SuiviFamilles\Service\Store;

$db = \OCP\Server::get(\OCP\IDBConnection::class);
$erreurs = 0;
foreach (Store::CHIFFRES as $table => $colonnes) {
    foreach ($colonnes as $col) {
        $qb = $db->getQueryBuilder();
        $qb->select($col)->from($table);
        $chiffrees = 0;
        $claires = 0;
        foreach ($qb->executeQuery()->fetchAll() as $ligne) {
            $v = $ligne[$col];
            if ($v === null || $v === '') { continue; }
            str_starts_with($v, Store::MARQUE) ? $chiffrees++ : $claires++;
        }
        printf("  %-4s %s.%s : %d chiffrée(s), %d en clair\n", $claires ? 'ÉCHEC' : 'OK', $table, $col, $chiffrees, $claires);
        if ($claires) { $erreurs++; }
    }
}
// La démo doit contenir au moins une situation, une note et un point de vigilance : on vérifie qu'ils
// ont bien été chiffrés (et donc qu'on ne teste pas une base vide).
$qb = $db->getQueryBuilder();
$qb->select('situation')->from('sf_familles');
$exemple = (string)($qb->executeQuery()->fetchOne() ?: '');
if (!str_starts_with($exemple, Store::MARQUE)) { echo "  ÉCHEC aucune situation chiffrée trouvée\n"; $erreurs++; }
echo $erreurs ? "CHIFFREMENT : $erreurs ÉCHEC(S)\n" : "CHIFFREMENT : TOUT EST BON\n";
exit($erreurs ? 1 : 0);
