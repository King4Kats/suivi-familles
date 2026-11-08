<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Controller;

use OCA\SuiviFamilles\Service\FamillesService;
use OCA\SuiviFamilles\Service\Interdit;
use OCA\SuiviFamilles\Service\Invalide;
use OCA\SuiviFamilles\Service\NonTrouve;
use OCA\SuiviFamilles\Service\Reglages;
use OCA\SuiviFamilles\Service\Tableaux;
// Controller : classe de base des contrôleurs Nextcloud (donne $this->request, etc.).
use OCP\AppFramework\Controller;
// Http : les constantes des codes HTTP (STATUS_NOT_FOUND = 404…).
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
// DataResponse : une réponse dont le contenu (tableau PHP) est envoyé en JSON au navigateur.
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * L'API JSON de l'app. Volontairement « bête » : elle lit la requête, appelle FamillesService
 * (qui vérifie les droits et applique les règles) et traduit ses erreurs en codes HTTP.
 *
 * #[NoAdminRequired] = ouvert à tout compte connecté ; le tri « qui voit quoi » est fait dans le service.
 * Les requêtes qui modifient (POST/PUT/DELETE) exigent en plus le jeton anti-CSRF de Nextcloud,
 * que js/main.js envoie dans l'en-tête « requesttoken ».
 *
 * #[...] est un « attribut » PHP 8 : une étiquette posée sur la méthode, que Nextcloud lit avant
 * de l'appeler. Sans #[NoAdminRequired], Nextcloud réserverait la méthode aux administrateurs.
 *
 * Chaque méthode publique correspond à une route de appinfo/routes.php :
 * 'api#fiche' → ApiController::fiche(). Les paramètres de la méthode ($id, $statut…) sont remplis
 * automatiquement par Nextcloud, depuis l'URL ({id}) ou le corps JSON, par leur NOM.
 * Le type (int $id) est converti au passage : « /api/familles/12 » donne $id = 12 (entier).
 */
class ApiController extends Controller {
    // $appName et $request sont exigés par la classe parente Controller ; les autres sont nos services,
    // fournis par injection de dépendances (promotion de propriétés : `private Tableaux $tableaux`…).
    public function __construct(string $appName, IRequest $request, private FamillesService $service, private Tableaux $tableaux, private Reglages $reglages, private IUserSession $userSession) {
        parent::__construct($appName, $request);
    }

    /** L'identifiant du compte connecté, ou '' si personne.
     *  `?->` (opérateur nullsafe) : si getUser() renvoie null, on n'appelle pas getUID() et on obtient null ;
     *  puis `?? ''` remplace ce null par une chaîne vide.
     *  On ne prend JAMAIS l'identité dans la requête : le navigateur pourrait mentir. */
    private function uid(): string {
        return $this->userSession->getUser()?->getUID() ?? '';
    }

    /** Corps JSON de la requête (+ paramètres d'URL, sans importance : le service filtre les champs). */
    private function donnees(): array {
        return $this->request->getParams();
    }

    /** Exécute une action et transforme ses erreurs métier en réponses HTTP propres.
     *  `callable` : on reçoit une FONCTION (ici les `fn () => …` des méthodes plus bas), qu'on appelle dans le try.
     *  Ainsi le try/catch est écrit une seule fois pour toute l'API.
     *  - pas d'erreur → 200 avec le résultat, ou { ok: true } si la méthode ne renvoie rien (void → null) ;
     *  - Invalide → 400, Interdit → 403, NonTrouve → 404, avec { message } affiché par le navigateur.
     *  Une autre erreur (bug, base en panne) n'est pas attrapée : Nextcloud la journalise et répond 500. */
    private function executer(callable $action): DataResponse {
        try {
            $r = $action();
            return new DataResponse($r ?? ['ok' => true]);
        } catch (Invalide $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (Interdit $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (NonTrouve $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        }
    }

    // ---------- synthèses ----------
    // Toutes les méthodes suivantes ont la même forme : une ligne qui appelle le service avec
    // le compte connecté, enveloppée dans executer(). `fn () => …` est une fonction fléchée :
    // elle n'est PAS exécutée ici, elle est transmise à executer() qui l'appelle dans son try.

    /** GET /api/accueil — « ma journée » (tout compte connecté). */
    #[NoAdminRequired]
    public function accueil(): DataResponse {
        return $this->executer(fn () => $this->tableaux->accueil($this->uid()));
    }

    /** GET /api/reglages — listes de choix des formulaires. */
    #[NoAdminRequired]
    public function reglages(): DataResponse {
        return $this->executer(fn () => $this->reglages->tous());
    }

    // ---------- familles ----------

    /** GET /api/familles — les familles visibles + « suis-je direction ? » (pour l'affichage). */
    #[NoAdminRequired]
    public function familles(): DataResponse {
        return $this->executer(fn () => ['direction' => $this->service->estDirection($this->uid()), 'familles' => $this->service->liste($this->uid())]);
    }

    /** POST /api/familles — créer une famille (direction). */
    #[NoAdminRequired]
    public function familleCreer(): DataResponse {
        return $this->executer(fn () => $this->service->creerFamille($this->uid(), $this->donnees()));
    }

    /** GET /api/familles/{id} — fiche complète (404 si inaccessible). */
    #[NoAdminRequired]
    public function fiche(int $id): DataResponse {
        return $this->executer(fn () => $this->service->fiche($this->uid(), $id));
    }

    /** PUT /api/familles/{id} — modifier la fiche (direction ou « peut modifier »). */
    #[NoAdminRequired]
    public function familleModifier(int $id): DataResponse {
        return $this->executer(fn () => $this->service->modifierFamille($this->uid(), $id, $this->donnees()));
    }

    /** DELETE /api/familles/{id} — supprimer (direction). */
    #[NoAdminRequired]
    public function familleSupprimer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->supprimerFamille($this->uid(), $id));
    }

    /** PUT /api/familles/{id}/statut — clore ou rouvrir (direction).
     *  Les valeurs par défaut (= '') évitent une erreur si le navigateur n'envoie pas un champ. */
    #[NoAdminRequired]
    public function statut(int $id, string $statut = '', string $dateFin = '', string $motif = ''): DataResponse {
        return $this->executer(fn () => $this->service->changerStatut($this->uid(), $id, $statut, $dateFin, $motif));
    }

    /** PUT /api/familles/{id}/referent — choisir le référent (direction). */
    #[NoAdminRequired]
    public function referent(int $id, string $userId = ''): DataResponse {
        return $this->executer(fn () => $this->service->definirReferent($this->uid(), $id, $userId));
    }

    /** POST /api/familles/{id}/impression — noter l'impression au journal. */
    #[NoAdminRequired]
    public function impression(int $id): DataResponse {
        return $this->executer(fn () => $this->service->impression($this->uid(), $id));
    }

    // ---------- membres ----------
    // Attention : dans membreCreer, {id} est l'id de la FAMILLE ; dans membreModifier/Supprimer, celui du MEMBRE.
    // (Même logique pour suivis, actions, objectifs et accès.)

    #[NoAdminRequired]
    public function membreCreer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->creerMembre($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function membreModifier(int $id): DataResponse {
        return $this->executer(fn () => $this->service->modifierMembre($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function membreSupprimer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->supprimerMembre($this->uid(), $id));
    }

    // ---------- suivis ----------

    #[NoAdminRequired]
    public function suiviCreer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->creerSuivi($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function suiviModifier(int $id): DataResponse {
        return $this->executer(fn () => $this->service->modifierSuivi($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function suiviSupprimer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->supprimerSuivi($this->uid(), $id));
    }

    // ---------- actions ----------

    #[NoAdminRequired]
    public function actionCreer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->creerAction($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function actionModifier(int $id): DataResponse {
        return $this->executer(fn () => $this->service->modifierAction($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function actionSupprimer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->supprimerAction($this->uid(), $id));
    }

    // ---------- objectifs ----------

    #[NoAdminRequired]
    public function objectifCreer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->creerObjectif($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function objectifModifier(int $id): DataResponse {
        return $this->executer(fn () => $this->service->modifierObjectif($this->uid(), $id, $this->donnees()));
    }

    #[NoAdminRequired]
    public function objectifSupprimer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->supprimerObjectif($this->uid(), $id));
    }

    // ---------- équipe ----------
    // Toutes réservées à la direction (vérifié dans le service, pas ici).

    /** POST /api/familles/{id}/acces — ouvrir la famille à un compte. */
    #[NoAdminRequired]
    public function accesAjouter(int $id, string $userId = '', string $fonction = '', bool $peutModifier = false): DataResponse {
        return $this->executer(fn () => $this->service->ajouterAcces($this->uid(), $id, $userId, $fonction, $peutModifier));
    }

    /** PUT /api/acces/{id} — changer fonction / « peut modifier » ({id} = id de l'accès). */
    #[NoAdminRequired]
    public function accesModifier(int $id, string $fonction = '', bool $peutModifier = false): DataResponse {
        return $this->executer(fn () => $this->service->modifierAcces($this->uid(), $id, $fonction, $peutModifier));
    }

    /** DELETE /api/acces/{id} — retirer l'accès. */
    #[NoAdminRequired]
    public function accesRetirer(int $id): DataResponse {
        return $this->executer(fn () => $this->service->retirerAcces($this->uid(), $id));
    }

    /** GET /api/utilisateurs?q=… — recherche de comptes Nextcloud. */
    #[NoAdminRequired]
    public function utilisateurs(string $q = ''): DataResponse {
        return $this->executer(fn () => $this->service->chercherComptes($this->uid(), $q));
    }

    // ---------- journal ----------

    /** GET /api/journal (?famille=12 pour une seule famille) — direction.
     *  `?int $famille = null` : paramètre facultatif ; absent de l'URL, il vaut null (= toutes les familles). */
    #[NoAdminRequired]
    public function journal(?int $famille = null): DataResponse {
        return $this->executer(fn () => $this->service->journal($this->uid(), $famille));
    }
}
