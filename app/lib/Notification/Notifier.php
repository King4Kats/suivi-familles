<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Notification;

use OCA\SuiviFamilles\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Met en forme la notification de l'app dans la cloche de Nextcloud :
 *  « acces » : « Nouvelle famille confiée : Famille Martin », clic → la fiche.
 *  « action » : « Action confiée : Appeler l'école (pour le 12/11/2026) », clic → l'onglet Actions.
 *
 * Fonctionnement en deux temps :
 *  1) FamillesService::notifier() ENREGISTRE la notification : un sujet ('acces' ou 'action')
 *     et des paramètres (nom de la famille, titre…), sans aucune phrase.
 *  2) Quand la cloche s'affiche, Nextcloud appelle prepare() ci-dessous pour fabriquer le texte.
 *
 * `implements INotifier` : la classe s'engage à fournir les méthodes de cette interface
 * (getID, getName, prepare). Elle est déclarée à Nextcloud dans AppInfo/Application::register().
 */
class Notifier implements INotifier {
    // IURLGenerator : fabrique des adresses complètes (https://…/apps/suivi_familles/).
    public function __construct(private IURLGenerator $url) {
    }

    /** Identifiant unique de ce « notifier » : l'id de l'app. */
    public function getID(): string {
        return Application::APP_ID;
    }

    /** Nom lisible, affiché dans les réglages de notification de l'utilisateur. */
    public function getName(): string {
        return 'Familles';
    }

    /**
     * Transforme une notification brute en texte affichable.
     * Nextcloud appelle prepare() de TOUTES les apps pour chaque notification : on doit refuser
     * celles qui ne sont pas à nous en lançant UnknownNotificationException.
     */
    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== Application::APP_ID || !in_array($notification->getSubject(), ['acces', 'action'], true)) {
            throw new UnknownNotificationException(); // pas à nous : Nextcloud passe au suivant
        }
        // Les paramètres enregistrés par FamillesService::notifier (famille, par, titre, echeance, id).
        $p = $notification->getSubjectParameters();
        // Adresse écrite en dur (et non par le nom de route) : les routes de l'app ne sont pas
        // forcément chargées quand la cloche prépare la notification.
        $page = $this->url->getAbsoluteURL('/apps/' . Application::APP_ID . '/');
        $notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));
        if ($notification->getSubject() === 'acces') {
            // Famille confiée. Le lien « #famille/12 » est lu par js/main.js pour ouvrir la bonne fiche.
            $notification->setParsedSubject('Nouvelle famille confiée : ' . ($p['famille'] ?? ''));
            $notification->setParsedMessage('Accès ouvert par ' . ($p['par'] ?? 'la direction'));
            $notification->setLink($page . '#famille/' . (int)($p['id'] ?? 0));
        } else {
            // Action confiée. Échéance '2026-11-12' remise en '12/11/2026' si elle existe.
            $echeance = !empty($p['echeance']) ? ' (pour le ' . implode('/', array_reverse(explode('-', $p['echeance']))) . ')' : '';
            $notification->setParsedSubject('Action confiée : ' . ($p['titre'] ?? '') . $echeance);
            $notification->setParsedMessage(($p['famille'] ?? '') . ' · par ' . ($p['par'] ?? ''));
            $notification->setLink($page . '#famille/' . (int)($p['id'] ?? 0) . '/actions');
        }
        return $notification;
    }
}
