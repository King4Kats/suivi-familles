<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Controller;

use OCA\SuiviFamilles\AppInfo\Application;
use OCA\SuiviFamilles\Service\FamillesService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
// TemplateResponse : une réponse HTML fabriquée à partir d'un fichier de templates/.
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

/**
 * Sert l'unique page de l'app (templates/main.php) et charge js/main.js + css/main.css.
 * Le drapeau « direction » ne sert qu'à l'affichage (onglets, boutons) : l'API revérifie tout.
 */
class PageController extends Controller {
    // Injection de dépendances : Nextcloud fournit le service et la session (promotion de propriétés).
    public function __construct(string $appName, IRequest $request, private FamillesService $service, private IUserSession $userSession) {
        parent::__construct($appName, $request);
    }

    /**
     * GET / (route 'page#index') — la page HTML de l'app.
     * #[NoAdminRequired] : ouverte à tout compte connecté.
     * #[NoCSRFRequired] : on arrive ici en cliquant sur l'icône de l'app ; ce simple affichage
     * ne peut pas porter de jeton anti-CSRF (et il ne modifie rien, donc sans risque).
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        // Ajoute <script src="js/main.js"> et <link href="css/main.css"> à la page.
        Util::addScript(Application::APP_ID, 'main');
        Util::addStyle(Application::APP_ID, 'main');
        // Compte connecté (`?->` : nullsafe, voir ApiController::uid).
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        // Le 3e argument : variables transmises au template templates/main.php.
        return new TemplateResponse(Application::APP_ID, 'main', ['direction' => $this->service->estDirection($uid), 'uid' => $uid, 'nom' => $this->service->nom($uid)]);
    }
}
