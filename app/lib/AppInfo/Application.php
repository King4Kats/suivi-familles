<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\AppInfo;

use OCA\SuiviFamilles\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Point d'entrée de l'app « Familles » (suivi_familles).
 * Autonome : ses propres tables (sf_familles, sf_membres, sf_acces, sf_suivis, sf_journal — voir
 * lib/Migration), ses notifications (cloche Nextcloud : « vous avez accès à une famille »).
 * (Depuis la 0.2 : aussi sf_actions et sf_objectifs, soit 7 tables.)
 *
 * Organisation du code :
 *   Controller/  reçoit les requêtes HTTP, renvoie du JSON (aucune règle métier)
 *   Service/FamillesService  LES RÈGLES : qui voit quoi, validations, journal, notifications
 *   Service/Store            la base de données, rien d'autre
 *
 * IBootstrap : Nextcloud appelle register() puis boot() au démarrage de l'app.
 * Nextcloud trouve cette classe grâce à son nom : OCA\<App>\AppInfo\Application.
 */
class Application extends App implements IBootstrap {
    // L'identifiant de l'app (le même que dans appinfo/info.xml). Réutilisé partout : Application::APP_ID.
    public const APP_ID = 'suivi_familles';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    /**
     * Phase « inscription » : on déclare à Nextcloud ce que l'app apporte, sans rien exécuter.
     * Ici : notre Notifier, pour que la cloche sache afficher nos notifications.
     * Notifier::class = le nom complet de la classe, sous forme de texte.
     */
    public function register(IRegistrationContext $context): void {
        $context->registerNotifierService(Notifier::class);
    }

    /** Phase « démarrage » : rien à faire pour cette app (méthode exigée par IBootstrap). */
    public function boot(IBootContext $context): void {
    }
}
