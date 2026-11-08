<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

// declare(strict_types=1) : PHP refuse de convertir les types en silence.
// Exemple : passer la chaîne "12" à un paramètre `int` lève une TypeError au lieu de deviner.
// Toujours en 1re instruction du fichier.
declare(strict_types=1);

// namespace : « l'adresse » de la classe. Nextcloud range le code d'une app sous OCA\<NomApp>\...
// Le chargement automatique (autoload) retrouve le fichier à partir de ce chemin :
// OCA\SuiviFamilles\Service\FamillesService → lib/Service/FamillesService.php
namespace OCA\SuiviFamilles\Service;

// use : raccourcis vers des classes d'autres espaces de noms (on écrit IAppConfig au lieu du nom complet).
// OCP\... = l'API publique et stable de Nextcloud, celle qu'une app a le droit d'utiliser.
use OCA\SuiviFamilles\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
// « as » renomme localement : IManager est trop vague, on l'appelle INotificationManager.
use OCP\Notification\IManager as INotificationManager;

/**
 * LES RÈGLES DE L'APP « FAMILLES ».
 *
 * Deux profils :
 *  - DIRECTION : administrateurs Nextcloud + membres des groupes listés dans le réglage
 *    `occ config:app:set suivi_familles groupes_direction --value="direction"` (séparés par des virgules).
 *    Voit et gère TOUTES les familles, leur équipe, et le journal.
 *  - INTERVENANT : toute personne à qui la direction a ouvert une famille (une ligne dans sf_acces).
 *    Voit SEULEMENT ces familles. Écrit des notes de suivi, gère les actions à faire de l'équipe.
 *    Ne modifie la fiche (coordonnées, membres, objectifs) que si la direction a coché « peut modifier ».
 *
 * Notes « privées » : visibles seulement par leur auteur et la direction.
 *
 * Chaque méthode publique commence par vérifier les droits (exiger...) : le navigateur peut
 * cacher des boutons, mais c'est ICI que l'accès est réellement refusé.
 *
 * Les erreurs passent par trois exceptions maison, que ApiController traduit en codes HTTP :
 *  - Invalide  → 400 (saisie incorrecte, message montré à l'utilisateur)
 *  - Interdit  → 403 (on voit la famille, mais on n'a pas le droit de faire ÇA)
 *  - NonTrouve → 404 (n'existe pas OU pas d'accès : volontairement indiscernables)
 *
 * Le journal (table sf_journal) : chaque écriture et chaque consultation y laisse une ligne
 * « qui, quoi, quand ». On y note les NOMS des champs changés, jamais les valeurs (données sensibles).
 */
class FamillesService {
    // Petit cache « uid → nom affiché » : la même personne revient souvent dans une liste,
    // on ne redemande pas son nom à Nextcloud à chaque fois. Il vit le temps d'une requête HTTP.
    private array $nomsCache = [];

    /**
     * Injection de dépendances : on ne crée jamais ces objets nous-mêmes (pas de `new Store()`).
     * Nextcloud lit les types des paramètres du constructeur et fournit l'objet correspondant.
     *
     * « Promotion de propriétés » (PHP 8) : écrire `private Store $store` DANS la parenthèse
     * déclare la propriété $this->store ET la remplit, d'où le corps vide `{ }`.
     */
    public function __construct(
        private Store $store,                          // lectures/écritures en base
        private IGroupManager $groups,                 // « est-il admin ? est-il dans tel groupe ? »
        private IUserManager $users,                   // retrouver un compte, chercher des comptes
        private INotificationManager $notifications,   // envoyer dans la cloche Nextcloud
        private IAppConfig $config,                    // réglages de l'app (occ config:app:set)
    ) {
    }

    // =====================================================================
    // Qui a le droit ?
    // =====================================================================

    /**
     * Ce compte fait-il partie de la direction ?
     * Oui s'il est administrateur Nextcloud, ou membre d'un des groupes du réglage `groupes_direction`.
     * Renvoie un booléen ; ne lance jamais d'exception.
     */
    public function estDirection(string $uid): bool {
        // Compte vide (personne de connecté) : jamais direction.
        if ($uid === '') { return false; }
        // Les administrateurs Nextcloud sont toujours direction.
        if ($this->groups->isAdmin($uid)) { return true; }
        // Le réglage est une chaîne « direction,bureau » ; 'direction' si rien n'a été réglé.
        $groupes = $this->config->getValueString(Application::APP_ID, 'groupes_direction', 'direction');
        // explode découpe sur les virgules, array_map('trim', …) enlève les espaces autour de chaque nom,
        // array_filter (sans fonction) retire les éléments vides (ex. « a,,b » ou virgule finale).
        foreach (array_filter(array_map('trim', explode(',', $groupes))) as $gid) {
            if ($this->groups->isInGroup($uid, $gid)) { return true; }
        }
        return false;
    }

    /**
     * Droits de $uid sur une famille : ['voir' => bool, 'modifier' => bool, 'direction' => bool].
     * - direction : tout à true ;
     * - intervenant : « voir » s'il a une ligne dans sf_acces, « modifier » si en plus « peut modifier » est coché ;
     * - sinon : tout à false.
     * Ne vérifie PAS que la famille existe (c'est le rôle d'exigerVoir).
     */
    public function droits(string $uid, int $familleId): array {
        if ($this->estDirection($uid)) { return ['voir' => true, 'modifier' => true, 'direction' => true]; }
        // $a = la ligne d'accès de cette personne pour cette famille, ou null si aucune.
        $a = $this->store->accesDe($familleId, $uid);
        return ['voir' => $a !== null, 'modifier' => $a !== null && $a['peutModifier'], 'direction' => false];
    }

    /**
     * Ids des familles que $uid peut voir (null = toutes : direction).
     * Le type de retour `?array` veut dire « un tableau OU null ».
     * array_keys : accesPersonne() renvoie [familleId => accès], on ne garde que les clés (les ids).
     */
    public function idsVisibles(string $uid): ?array {
        return $this->estDirection($uid) ? null : array_keys($this->store->accesPersonne($uid));
    }

    /** Charge la famille et vérifie qu'on peut la voir. 404 identique si elle n'existe pas OU si
     *  on n'y a pas accès : on ne révèle pas l'existence d'une famille à qui n'y a pas droit.
     *  (Avec un 403, un curieux pourrait essayer les ids 1, 2, 3… et savoir lesquels existent.)
     *  Renvoie la famille (tableau) ; lance NonTrouve (404) sinon.
     *  Toutes les méthodes qui touchent une famille passent par ici ou par exigerModifier. */
    private function exigerVoir(string $uid, int $familleId): array {
        $f = $this->store->famille($familleId);
        if ($f === null || !$this->droits($uid, $familleId)['voir']) { throw new NonTrouve('Famille introuvable'); }
        return $f;
    }

    /**
     * Comme exigerVoir, puis vérifie en plus le droit de modifier la fiche
     * (direction, ou intervenant avec « peut modifier » coché).
     * Ordre voulu : d'abord « la voit-il ? » (sinon 404), ensuite « peut-il modifier ? » (sinon 403).
     * Un 403 n'apprend rien ici : la personne voit déjà la famille.
     */
    private function exigerModifier(string $uid, int $familleId): array {
        $f = $this->exigerVoir($uid, $familleId);
        if (!$this->droits($uid, $familleId)['modifier']) { throw new Interdit('Vous ne pouvez pas modifier cette fiche'); }
        return $f;
    }

    /** Lance Interdit (403) si $uid n'est pas direction. `void` : ne renvoie rien, sert juste de barrière. */
    public function exigerDirection(string $uid): void {
        if (!$this->estDirection($uid)) { throw new Interdit('Réservé à la direction'); }
    }

    /** Nom affiché d'un compte (mis en cache le temps de la requête).
     *  `?string` : accepte aussi null (ex. une famille sans référent). Renvoie '' dans ce cas. */
    public function nom(?string $uid): string {
        if ($uid === null || $uid === '') { return ''; }
        if (!isset($this->nomsCache[$uid])) {
            $u = $this->users->get($uid);
            // Compte supprimé depuis ($u vaut null) : on affiche au moins l'identifiant.
            $this->nomsCache[$uid] = $u ? $u->getDisplayName() : $uid;
        }
        return $this->nomsCache[$uid];
    }

    /** « peut-il voir cette famille ? » pour un AUTRE compte (choix d'un responsable, d'un référent).
     *  Le compte doit exister ET avoir accès à la famille. Évite de confier une famille à quelqu'un
     *  qui ne pourrait même pas l'ouvrir. */
    private function peutVoir(string $cible, int $familleId): bool {
        return $this->users->get($cible) !== null && $this->droits($cible, $familleId)['voir'];
    }

    // =====================================================================
    // Lecture
    // =====================================================================

    /** Les besoins sont rangés en base en une seule chaîne « a|b|c ».
     *  On la redécoupe en tableau ['a', 'b', 'c'] (sans les morceaux vides).
     *  `static` : s'appelle sans objet, par self::besoins(...).
     *  array_values renumérote les clés 0, 1, 2… après le filtrage (sinon le JSON serait un objet, pas une liste). */
    public static function besoins(?string $s): array {
        return array_values(array_filter(array_map('trim', explode('|', (string)$s))));
    }

    /**
     * Familles visibles par $uid, avec les infos de synthèse de la liste.
     * Qui : tout le monde. La direction reçoit toutes les familles (+ l'équipe de chacune),
     * un intervenant seulement celles qui lui sont ouvertes.
     * Renvoie une liste de tableaux prêts pour le JSON.
     */
    public function liste(string $uid): array {
        // 1) Quelles familles charger ?
        $direction = $this->estDirection($uid);
        $mesAcces = $this->store->accesPersonne($uid);
        $familles = $direction ? $this->store->familles() : $this->store->familles(array_keys($mesAcces));

        // 2) Les compléments, chargés en une requête chacun pour TOUTES les familles
        //    (plutôt qu'une requête par famille : bien plus rapide quand la liste s'allonge).
        // array_column : extrait la colonne 'id' de chaque ligne → [3, 7, 12…].
        $ids = array_column($familles, 'id');
        $nb = $this->store->nbMembres();
        $derniers = $this->store->derniersSuivis();
        $tousAcces = $direction ? $this->store->accesTous() : [];

        // 3) Prochaine action de chaque famille.
        // `??=` : « affecte seulement si la case est vide (null ou absente) ». On garde donc la 1re rencontrée.
        $prochaines = [];
        foreach ($this->store->actionsOuvertes($ids) as $a) { $prochaines[$a['familleId']] ??= $a; }   // déjà triées : la 1re est la plus proche

        // 4) On construit une ligne de résultat par famille.
        // array_map applique la fonction à chaque famille. `use (...)` : une fonction anonyme classique
        // ne voit pas les variables extérieures, il faut les lui passer explicitement.
        return array_map(function ($f) use ($direction, $mesAcces, $nb, $derniers, $tousAcces, $prochaines) {
            $id = $f['id'];
            // `??` : « valeur de gauche si elle existe et n'est pas null, sinon celle de droite ».
            $dernier = $derniers[$id] ?? null;
            $p = $prochaines[$id] ?? null;
            $res = [
                'id' => $id, 'nom' => $f['nom'], 'statut' => $f['statut'], 'dateEntree' => $f['dateEntree'], 'dateFin' => $f['dateFin'],
                'commune' => $f['commune'], 'territoire' => $f['territoire'], 'besoins' => self::besoins($f['besoins']),
                'referent' => $f['referent'], 'referentNom' => $this->nom($f['referent']),
                'nbMembres' => $nb[$id] ?? 0, 'dernierSuivi' => $dernier,
                // Alerte : accompagnement actif sans date de consentement enregistrée.
                'sansConsentement' => $f['statut'] === 'actif' && !$f['consentement'],
                // On n'envoie que le strict utile de la prochaine action.
                'prochaine' => $p ? ['titre' => $p['titre'], 'echeance' => $p['echeance'], 'heure' => $p['heure'], 'genre' => $p['genre']] : null,
                // Ma fonction dans cette famille (null pour la direction qui n'est pas dans l'équipe).
                'maFonction' => $mesAcces[$id]['fonction'] ?? null,
            ];
            if ($direction) {
                // Pour la direction : l'équipe de chaque famille, en résumé.
                // `fn ($a) => …` : fonction fléchée (closure courte). Contrairement à function(){},
                // elle voit automatiquement les variables extérieures, dont $this.
                $res['equipe'] = array_map(fn ($a) => ['nom' => $this->nom($a['userId']), 'fonction' => $a['fonction']], $tousAcces[$id] ?? []);
            }
            return $res;
        }, $familles);
    }

    /** Fiche complète. Chaque consultation est notée au journal (au plus une fois par 15 min et par personne).
     *  Qui : toute personne qui voit la famille (sinon NonTrouve → 404).
     *  Renvoie la famille, ses membres, son équipe, ses notes, actions, objectifs, et les droits de $uid
     *  (le navigateur s'en sert pour afficher ou cacher les boutons ; le serveur revérifie quand même). */
    public function fiche(string $uid, int $familleId): array {
        $f = $this->exigerVoir($uid, $familleId);
        $droits = $this->droits($uid, $familleId);

        // Journal de consultation, sans en noter une à chaque rechargement de page :
        // on n'écrit que si la dernière consultation de cette personne date de plus de 15 minutes.
        $derniere = $this->store->derniereAction($familleId, $uid, 'consultation');
        if ($derniere === null || time() - $derniere > 15 * 60) {
            $this->store->journaliser($familleId, $uid, 'consultation');
        }

        // Équipe : chaque ligne d'accès + le nom affiché. `+` sur des tableaux = ajoute les clés manquantes.
        $equipe = array_map(fn ($a) => $a + ['nom' => $this->nom($a['userId'])], $this->store->accesFamille($familleId));
        // Notes privées : on ne les envoie même pas au navigateur de ceux qui ne doivent pas les lire.
        // On garde une note si elle n'est pas privée, OU si j'en suis l'auteur, OU si je suis direction.
        $suivis = array_values(array_filter($this->store->suivis($familleId), fn ($s) => !$s['prive'] || $s['auteur'] === $uid || $droits['direction']));
        // Pour chaque note : nom de l'auteur + « puis-je la modifier ? » (même règle que exigerSuiviModifiable).
        $suivis = array_map(fn ($s) => $s + [
            'auteurNom' => $this->nom($s['auteur']),
            'peutModifier' => $s['auteur'] === $uid || $droits['direction'],
        ], $suivis);
        // Pour chaque action : nom du responsable + « puis-je la supprimer ? » (créateur ou direction).
        $actions = array_map(fn ($a) => $a + [
            'responsableNom' => $this->nom($a['responsable']),
            'peutSupprimer' => $a['createdBy'] === $uid || $droits['direction'],
        ], $this->store->actions($familleId));

        // Personnes à qui on peut confier une action : l'équipe + moi (la direction n'est pas forcément dans l'équipe).
        // Tableau [uid => nom] : la clé évite les doublons ; `??=` n'écrase pas un nom déjà présent.
        $personnes = [];
        foreach ($equipe as $a) { $personnes[$a['userId']] = $a['nom']; }
        $personnes[$uid] ??= $this->nom($uid);
        if ($f['referent']) { $personnes[$f['referent']] ??= $this->nom($f['referent']); }

        // Mise en forme de la famille pour le navigateur.
        $f['besoins'] = self::besoins($f['besoins']);
        $f['referentNom'] = $this->nom($f['referent']);
        $derniers = $this->store->derniersSuivis();

        return [
            'famille' => $f,
            'dernierSuivi' => $derniers[$familleId] ?? null,
            'membres' => $this->store->membres($familleId),
            'equipe' => $equipe,
            'suivis' => $suivis,
            'actions' => $actions,
            'objectifs' => $this->store->objectifs($familleId),
            // array_map avec DEUX tableaux : la fonction reçoit un élément de chacun (clé, nom) à chaque tour.
            // (string)$id : une clé numérique devient un int en PHP, on la remet en texte comme un uid.
            'personnes' => array_map(fn ($id, $nom) => ['id' => (string)$id, 'nom' => $nom], array_keys($personnes), $personnes),
            'droits' => $droits,
        ];
    }

    /** Trace une impression de la fiche (c'est une sortie de données : elle doit apparaître au journal).
     *  Qui : toute personne qui voit la famille. Ne renvoie rien (l'API répondra { ok: true }). */
    public function impression(string $uid, int $familleId): void {
        $this->exigerVoir($uid, $familleId);
        $this->store->journaliser($familleId, $uid, 'impression');
    }

    // =====================================================================
    // Familles
    // =====================================================================

    /**
     * Crée une famille. Qui : direction seulement (Interdit sinon).
     * Les données du formulaire sont nettoyées (Invalide si le nom manque, e-mail faux…).
     * Une nouvelle famille est toujours « actif ». Renvoie la famille telle qu'enregistrée.
     */
    public function creerFamille(string $uid, array $donnees): array {
        $this->exigerDirection($uid);
        $champs = $this->nettoyerFamille($donnees, true);
        $champs['statut'] = 'actif';
        $id = $this->store->creerFamille($champs, $uid);
        $this->store->journaliser($id, $uid, 'creation', $champs['nom']);
        return $this->store->famille($id);
    }

    /**
     * Modifie la fiche (coordonnées, situation, besoins…).
     * Qui : direction, ou intervenant avec « peut modifier » (Interdit / NonTrouve sinon).
     * $avant sert à noter au journal les champs réellement changés. Renvoie la famille à jour.
     */
    public function modifierFamille(string $uid, int $familleId, array $donnees): array {
        $avant = $this->exigerModifier($uid, $familleId);
        $champs = $this->nettoyerFamille($donnees, false);
        $this->store->modifierFamille($familleId, $champs);
        $this->store->journaliser($familleId, $uid, 'modification', $this->champsModifies($avant, $champs));
        return $this->store->famille($familleId);
    }

    /** Clore (avec date et motif) ou rouvrir un accompagnement : décision de la direction.
     *  $statut doit valoir 'clos' ou 'actif' (Invalide sinon). Renvoie la famille à jour. */
    public function changerStatut(string $uid, int $familleId, string $statut, string $dateFin, string $motif): array {
        $this->exigerDirection($uid);
        $f = $this->exigerVoir($uid, $familleId);
        if ($statut === 'clos') {
            // Clôture. `?:` (opérateur « Elvis ») : si la date saisie est vide (''), on prend aujourd'hui.
            $dateFin = $this->date($dateFin, 'Date de fin') ?: date('Y-m-d');
            $motif = $this->texte($motif, 255);
            $this->store->modifierFamille($familleId, ['statut' => 'clos', 'date_fin' => $dateFin, 'motif_fin' => $motif]);
            $this->store->journaliser($familleId, $uid, 'cloture', 'Fin le ' . $this->dateFr($dateFin) . ($motif !== '' ? ' : ' . $motif : ''));
        } elseif ($statut === 'actif') {
            // Réouverture : rien à faire si elle est déjà active (pas de ligne de journal inutile).
            if ($f['statut'] === 'actif') { return $f; }
            $this->store->modifierFamille($familleId, ['statut' => 'actif', 'date_fin' => null, 'motif_fin' => null]);
            $this->store->journaliser($familleId, $uid, 'reouverture');
        } else {
            // Toute autre valeur vient d'une requête bricolée : on refuse.
            throw new Invalide('Statut invalide');
        }
        return $this->store->famille($familleId);
    }

    /** Référent·e : la personne qui coordonne l'accompagnement. Doit pouvoir voir la famille.
     *  Qui : direction. $cible vide = plus de référent. Lance Invalide si la personne n'a pas accès.
     *  Renvoie la famille à jour. */
    public function definirReferent(string $uid, int $familleId, string $cible): array {
        $this->exigerDirection($uid);
        $this->exigerVoir($uid, $familleId);
        $cible = trim($cible);
        if ($cible !== '' && !$this->peutVoir($cible, $familleId)) { throw new Invalide('Le référent doit faire partie de l\'équipe de la famille'); }
        // En base, « pas de référent » = NULL (et non une chaîne vide).
        $this->store->modifierFamille($familleId, ['referent' => $cible === '' ? null : $cible]);
        $this->store->journaliser($familleId, $uid, 'referent', $cible === '' ? 'aucun' : $this->nom($cible));
        return $this->store->famille($familleId);
    }

    /**
     * Supprime définitivement la famille et tout ce qui en dépend (voir Store::supprimerFamille).
     * Qui : direction. Ne renvoie rien.
     * On lit la famille AVANT de la supprimer pour pouvoir écrire son nom au journal.
     */
    public function supprimerFamille(string $uid, int $familleId): void {
        $this->exigerDirection($uid);
        $f = $this->exigerVoir($uid, $familleId);
        $this->store->supprimerFamille($familleId);
        // Le journal garde la trace de la suppression (sans les données de la famille, effacées).
        $this->store->journaliser($familleId, $uid, 'suppression', 'Famille n° ' . $familleId . ' (' . $f['nom'] . ')');
    }

    // =====================================================================
    // Membres
    // =====================================================================

    /**
     * Ajoute un membre (parent, enfant…). Qui : direction ou intervenant « peut modifier ».
     * Le prénom est obligatoire (Invalide sinon). Renvoie le membre créé.
     */
    public function creerMembre(string $uid, int $familleId, array $donnees): array {
        $this->exigerModifier($uid, $familleId);
        $champs = $this->nettoyerMembre($donnees, true);
        $id = $this->store->creerMembre($familleId, $champs);
        // « Toucher » la famille = mettre à jour sa date de modification (updated_at).
        $this->store->toucherFamille($familleId);
        $this->store->journaliser($familleId, $uid, 'membre_ajout', $champs['prenom']);
        return $this->store->membre($id);
    }

    /**
     * Modifie un membre. Qui : direction ou intervenant « peut modifier » sur SA famille.
     * L'URL donne l'id du membre, pas de la famille : on retrouve d'abord le membre pour savoir
     * à quelle famille il appartient, puis on vérifie les droits sur CETTE famille.
     * Renvoie le membre à jour.
     */
    public function modifierMembre(string $uid, int $membreId, array $donnees): array {
        // `?? throw` (PHP 8) : si membre() renvoie null, on lance directement l'exception.
        $m = $this->store->membre($membreId) ?? throw new NonTrouve('Membre introuvable');
        $this->exigerModifier($uid, $m['familleId']);
        $champs = $this->nettoyerMembre($donnees, false);
        $this->store->modifierMembre($membreId, $champs);
        $this->store->toucherFamille($m['familleId']);
        $this->store->journaliser($m['familleId'], $uid, 'membre_modif', $m['prenom'] . ' : ' . $this->champsModifies($m, $champs));
        return $this->store->membre($membreId);
    }

    /** Supprime un membre (ses notes de suivi restent, rattachées à la famille).
     *  Qui : direction ou intervenant « peut modifier ». Ne renvoie rien. */
    public function supprimerMembre(string $uid, int $membreId): void {
        $m = $this->store->membre($membreId) ?? throw new NonTrouve('Membre introuvable');
        $this->exigerModifier($uid, $m['familleId']);
        $this->store->supprimerMembre($membreId);
        $this->store->toucherFamille($m['familleId']);
        $this->store->journaliser($m['familleId'], $uid, 'membre_suppr', $m['prenom']);
    }

    // =====================================================================
    // Notes de suivi (interventions)
    // =====================================================================

    /**
     * Écrit une note de suivi. Qui : toute personne qui voit la famille (pas besoin de « peut modifier » :
     * écrire ce qu'on a fait avec la famille est le cœur du travail de chaque intervenant).
     * L'auteur est toujours le compte connecté ($uid), jamais une valeur envoyée par le navigateur.
     * Renvoie la note créée.
     */
    public function creerSuivi(string $uid, int $familleId, array $d): array {
        $this->exigerVoir($uid, $familleId);   // toute personne de l'équipe peut écrire un suivi
        $champs = $this->nettoyerSuivi($familleId, $d);
        $id = $this->store->creerSuivi($familleId, $uid, $champs);
        // Le journal dit qu'une note a été écrite (type, date, « privée »), jamais son contenu.
        $this->store->journaliser($familleId, $uid, 'suivi_ajout', ($champs['type'] ?: 'Note') . ' du ' . $this->dateFr($champs['date_suivi']) . ($champs['prive'] ? ' (privée)' : ''));
        return $this->store->suivi($id);
    }

    /** Modifie une note. Qui : son auteur (s'il voit encore la famille) ou la direction. Renvoie la note à jour. */
    public function modifierSuivi(string $uid, int $suiviId, array $d): array {
        $s = $this->exigerSuiviModifiable($uid, $suiviId);
        // On revalide avec la famille de la note (et non une famille envoyée par le navigateur).
        $champs = $this->nettoyerSuivi($s['familleId'], $d);
        $this->store->modifierSuivi($suiviId, $champs);
        $this->store->journaliser($s['familleId'], $uid, 'suivi_modif', 'Note du ' . $this->dateFr($champs['date_suivi']));
        return $this->store->suivi($suiviId);
    }

    /** Supprime une note. Qui : son auteur ou la direction. Ne renvoie rien. */
    public function supprimerSuivi(string $uid, int $suiviId): void {
        $s = $this->exigerSuiviModifiable($uid, $suiviId);
        $this->store->supprimerSuivi($suiviId);
        $this->store->journaliser($s['familleId'], $uid, 'suivi_suppr', 'Note du ' . $this->dateFr($s['dateSuivi']));
    }

    /** Une note se modifie par son auteur (s'il a encore accès à la famille) ou par la direction.
     *  Lance NonTrouve (note absente ou famille invisible) ou Interdit (pas l'auteur). Renvoie la note.
     *  Remarque : une note privée d'un collègue n'est jamais envoyée au navigateur (voir fiche()). */
    private function exigerSuiviModifiable(string $uid, int $suiviId): array {
        $s = $this->store->suivi($suiviId) ?? throw new NonTrouve('Note introuvable');
        $this->exigerVoir($uid, $s['familleId']);
        if ($s['auteur'] !== $uid && !$this->estDirection($uid)) { throw new Interdit('Seul l\'auteur ou la direction peut modifier cette note'); }
        return $s;
    }

    // =====================================================================
    // Actions à faire et rendez-vous — toute l'équipe de la famille
    // =====================================================================

    /**
     * Crée une action à faire ou un rendez-vous. Qui : toute personne qui voit la famille.
     * Si l'action est confiée à quelqu'un d'autre, cette personne reçoit une notification.
     * Renvoie l'action créée.
     */
    public function creerAction(string $uid, int $familleId, array $d): array {
        $f = $this->exigerVoir($uid, $familleId);
        $champs = $this->nettoyerAction($familleId, $d, true);
        $id = $this->store->creerAction($familleId, $champs, $uid);
        $this->store->journaliser($familleId, $uid, 'action_ajout', $champs['titre']);
        // Notification seulement si un responsable est choisi ET que ce n'est pas moi
        // (inutile de se notifier soi-même).
        if (($champs['responsable'] ?? null) && $champs['responsable'] !== $uid) {
            $this->notifier($champs['responsable'], 'action', $familleId, ['famille' => $f['nom'], 'titre' => $champs['titre'], 'echeance' => $champs['echeance'] ?? '', 'par' => $this->nom($uid)]);
        }
        return $this->store->action($id);
    }

    /**
     * Modifie une action (texte, date, responsable) ou la coche « fait » / « pas fait ».
     * Qui : toute personne qui voit la famille. Renvoie l'action à jour.
     */
    public function modifierAction(string $uid, int $actionId, array $d): array {
        $a = $this->store->action($actionId) ?? throw new NonTrouve('Action introuvable');
        $f = $this->exigerVoir($uid, $a['familleId']);
        $champs = $this->nettoyerAction($a['familleId'], $d, false);

        // Case « fait » : on note aussi QUAND (horodatage), ou on l'efface si on décoche.
        // array_key_exists (et non isset) : la clé peut être présente avec la valeur false.
        if (array_key_exists('fait', $d)) {
            $champs['fait'] = (bool)$d['fait'];
            $champs['fait_le'] = $champs['fait'] ? time() : null;
        }
        $this->store->modifierAction($actionId, $champs);

        // Libellé du journal : si SEULS fait + fait_le ont changé (2 champs), c'est un simple cochage ;
        // sinon c'est une vraie modification.
        $quoi = isset($champs['fait']) && count($champs) === 2 ? ($champs['fait'] ? 'action_faite' : 'action_rouverte') : 'action_modif';
        $this->store->journaliser($a['familleId'], $uid, $quoi, $champs['titre'] ?? $a['titre']);

        // Notification : seulement si le responsable CHANGE vers quelqu'un d'autre que moi.
        // Pour titre et échéance, on prend la nouvelle valeur si elle a été envoyée, sinon l'ancienne (`??`).
        $nouveau = $champs['responsable'] ?? null;
        if ($nouveau && $nouveau !== $a['responsable'] && $nouveau !== $uid) {
            $this->notifier($nouveau, 'action', $a['familleId'], ['famille' => $f['nom'], 'titre' => $champs['titre'] ?? $a['titre'], 'echeance' => $champs['echeance'] ?? $a['echeance'] ?? '', 'par' => $this->nom($uid)]);
        }
        return $this->store->action($actionId);
    }

    /** Une action se supprime par la personne qui l'a créée ou par la direction.
     *  Lance NonTrouve ou Interdit. Ne renvoie rien. */
    public function supprimerAction(string $uid, int $actionId): void {
        $a = $this->store->action($actionId) ?? throw new NonTrouve('Action introuvable');
        $this->exigerVoir($uid, $a['familleId']);
        if ($a['createdBy'] !== $uid && !$this->estDirection($uid)) { throw new Interdit('Seule la personne qui l\'a créée ou la direction peut la supprimer'); }
        $this->store->supprimerAction($actionId);
        $this->store->journaliser($a['familleId'], $uid, 'action_suppr', $a['titre']);
    }

    // =====================================================================
    // Objectifs — mêmes droits que la fiche
    // =====================================================================

    /** Ajoute un objectif (statut « en cours »). Qui : direction ou intervenant « peut modifier ».
     *  Lance Invalide si le titre est vide. Renvoie l'objectif créé. */
    public function creerObjectif(string $uid, int $familleId, array $d): array {
        $this->exigerModifier($uid, $familleId);
        $titre = $this->texte($d['titre'] ?? '', 255);
        if ($titre === '') { throw new Invalide('L\'objectif est vide'); }
        $id = $this->store->creerObjectif($familleId, $titre);
        $this->store->journaliser($familleId, $uid, 'objectif_ajout', $titre);
        return $this->store->objectif($id);
    }

    /**
     * Modifie le titre et/ou le statut d'un objectif. Qui : direction ou intervenant « peut modifier ».
     * Seuls les champs présents dans la requête sont changés. Renvoie l'objectif à jour.
     */
    public function modifierObjectif(string $uid, int $objectifId, array $d): array {
        $o = $this->store->objectif($objectifId) ?? throw new NonTrouve('Objectif introuvable');
        $this->exigerModifier($uid, $o['familleId']);
        $champs = [];
        // Titre envoyé : il ne doit pas être vide.
        if (array_key_exists('titre', $d)) {
            $champs['titre'] = $this->texte($d['titre'], 255);
            if ($champs['titre'] === '') { throw new Invalide('L\'objectif est vide'); }
        }
        // Statut envoyé : uniquement l'une des trois valeurs prévues.
        // in_array(..., true) : comparaison stricte (même type et même valeur).
        if (array_key_exists('statut', $d)) {
            if (!in_array($d['statut'], ['en_cours', 'atteint', 'abandonne'], true)) { throw new Invalide('Statut invalide'); }
            $champs['statut'] = $d['statut'];
        }
        $this->store->modifierObjectif($objectifId, $champs);
        // Journal lisible : « Reprendre le sport : atteint ».
        $libelle = ['en_cours' => 'en cours', 'atteint' => 'atteint', 'abandonne' => 'abandonné'];
        $this->store->journaliser($o['familleId'], $uid, 'objectif_modif', ($champs['titre'] ?? $o['titre']) . (isset($champs['statut']) ? ' : ' . $libelle[$champs['statut']] : ''));
        return $this->store->objectif($objectifId);
    }

    /** Supprime un objectif. Qui : direction ou intervenant « peut modifier ». Ne renvoie rien. */
    public function supprimerObjectif(string $uid, int $objectifId): void {
        $o = $this->store->objectif($objectifId) ?? throw new NonTrouve('Objectif introuvable');
        $this->exigerModifier($uid, $o['familleId']);
        $this->store->supprimerObjectif($objectifId);
        $this->store->journaliser($o['familleId'], $uid, 'objectif_suppr', $o['titre']);
    }

    // =====================================================================
    // Équipe (accès) — direction seulement
    // =====================================================================

    /**
     * Ouvre la famille à un compte Nextcloud ($cible) : c'est ainsi qu'un intervenant entre dans l'équipe.
     * Qui : direction. Lance Invalide si le compte n'existe pas ou a déjà accès.
     * La personne reçoit une notification « Nouvelle famille confiée ».
     * Renvoie la ligne d'accès + le nom affiché.
     */
    public function ajouterAcces(string $uid, int $familleId, string $cible, string $fonction, bool $peutModifier): array {
        $this->exigerDirection($uid);
        $f = $this->exigerVoir($uid, $familleId);
        // Vérifications avant d'écrire.
        if ($this->users->get($cible) === null) { throw new Invalide('Compte inconnu'); }
        if ($this->store->accesDe($familleId, $cible) !== null) { throw new Invalide('Cette personne a déjà accès à la famille'); }
        $fonction = $this->texte($fonction, 80);
        $id = $this->store->ajouterAcces($familleId, $cible, $fonction, $peutModifier, $uid);
        $this->store->journaliser($familleId, $uid, 'acces_ajout', $this->nom($cible) . ($fonction !== '' ? ' (' . $fonction . ')' : ''));
        // Prévenir la personne (sauf si la direction s'ajoute elle-même).
        if ($cible !== $uid) { $this->notifier($cible, 'acces', $familleId, ['famille' => $f['nom'], 'par' => $this->nom($uid)]); }
        return $this->store->acces($id) + ['nom' => $this->nom($cible)];
    }

    /** Change la fonction d'un membre de l'équipe et sa case « peut modifier ». Qui : direction.
     *  Renvoie la ligne d'accès à jour + le nom. */
    public function modifierAcces(string $uid, int $accesId, string $fonction, bool $peutModifier): array {
        $this->exigerDirection($uid);
        $a = $this->store->acces($accesId) ?? throw new NonTrouve('Accès introuvable');
        $this->store->modifierAcces($accesId, $this->texte($fonction, 80), $peutModifier);
        $this->store->journaliser($a['familleId'], $uid, 'acces_modif', $this->nom($a['userId']) . ($peutModifier ? ' : peut modifier' : ' : lecture + suivis'));
        return $this->store->acces($accesId) + ['nom' => $this->nom($a['userId'])];
    }

    /** Retirer l'accès : la personne ne voit plus la famille ; si elle était référente, il n'y a plus de référent.
     *  Qui : direction. Ne renvoie rien.
     *  Ses notes et actions restent (elles font partie de l'histoire de l'accompagnement). */
    public function retirerAcces(string $uid, int $accesId): void {
        $this->exigerDirection($uid);
        $a = $this->store->acces($accesId) ?? throw new NonTrouve('Accès introuvable');
        $this->store->retirerAcces($accesId);
        // Un référent doit pouvoir voir la famille. Exception : un membre de la direction voit tout,
        // il peut donc rester référent même sans ligne d'accès.
        $f = $this->store->famille($a['familleId']);
        if ($f && $f['referent'] === $a['userId'] && !$this->estDirection($a['userId'])) {
            $this->store->modifierFamille($a['familleId'], ['referent' => null]);
        }
        $this->store->journaliser($a['familleId'], $uid, 'acces_retrait', $this->nom($a['userId']));
    }

    /** Recherche de comptes Nextcloud pour l'ajout à une équipe (nom affiché ou identifiant).
     *  Qui : direction (évite qu'un intervenant liste tous les comptes). Au moins 2 caractères.
     *  Renvoie au plus 15 résultats [['id' => …, 'nom' => …], …]. */
    public function chercherComptes(string $uid, string $q): array {
        $this->exigerDirection($uid);
        $q = trim($q);
        // mb_strlen compte les caractères (et non les octets : « é » = 1 caractère mais 2 octets).
        if (mb_strlen($q) < 2) { return []; }
        // Deux recherches (par nom affiché, puis par identifiant) fusionnées dans un tableau
        // indexé par uid : un compte trouvé deux fois n'apparaît qu'une fois.
        $trouves = [];
        foreach ($this->users->searchDisplayName($q, 15) as $u) { $trouves[$u->getUID()] = $u->getDisplayName(); }
        foreach ($this->users->search($q, 15) as $u) { $trouves[$u->getUID()] = $u->getDisplayName(); }
        // Mise en liste pour le JSON, coupée à 15.
        $out = [];
        foreach ($trouves as $id => $nom) { $out[] = ['id' => (string)$id, 'nom' => $nom]; }
        return array_slice($out, 0, 15);
    }

    // =====================================================================
    // Journal — direction seulement
    // =====================================================================

    /**
     * Le journal (200 dernières lignes), de toutes les familles ou d'une seule ($familleId).
     * Qui : direction. Chaque ligne reçoit le nom de la personne et le nom de la famille
     * (null si la famille a été supprimée depuis : la ligne de journal, elle, reste).
     */
    public function journal(string $uid, ?int $familleId): array {
        $this->exigerDirection($uid);
        // Table de correspondance [id => nom] de toutes les familles, chargée une seule fois.
        $nomsFamilles = [];
        foreach ($this->store->familles() as $f) { $nomsFamilles[$f['id']] = $f['nom']; }
        return array_map(fn ($j) => $j + ['nom' => $this->nom($j['userId']), 'famille' => $nomsFamilles[$j['familleId']] ?? null], $this->store->journal($familleId));
    }

    // =====================================================================
    // Nettoyage des saisies
    // =====================================================================
    // Règle d'or : ne jamais faire confiance à ce qu'envoie le navigateur. On garde seulement
    // les champs prévus, on coupe les textes trop longs, on vérifie dates, e-mails, heures.
    // En cas de problème : exception Invalide, dont le message s'affiche tel quel à l'utilisateur.

    /** Texte nettoyé : converti en chaîne, espaces de début/fin retirés, coupé à $max caractères.
     *  `mixed` : accepte n'importe quel type (null, nombre…). mb_substr coupe sans casser les accents. */
    public function texte(mixed $v, int $max): string {
        return mb_substr(trim((string)($v ?? '')), 0, $max);
    }

    /** 'AAAA-MM-JJ' valide, ou '' ; lève une erreur si la saisie est non vide mais fausse.
     *  $libelle sert au message d'erreur (« Date de naissance : date invalide »). */
    public function date(mixed $v, string $libelle): string {
        $v = trim((string)($v ?? ''));
        if ($v === '') { return ''; }
        // Le « ! » remet l'heure à zéro. On relit ensuite la date et on la compare à la saisie :
        // PHP accepterait « 2026-02-31 » en le transformant en 3 mars, la comparaison le refuse.
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if ($d === false || $d->format('Y-m-d') !== $v) { throw new Invalide($libelle . ' : date invalide'); }
        return $v;
    }

    /** '2026-11-08' → '08/11/2026' (pour le journal). '' si pas de date. */
    private function dateFr(?string $iso): string {
        return $iso ? implode('/', array_reverse(explode('-', $iso))) : '';
    }

    /** Ne garde que les champs autorisés et présents. $creation : le nom est alors obligatoire.
     *  Entrée : clés « JavaScript » (dateEntree) ; sortie : colonnes SQL (date_entree).
     *  Lance Invalide (nom vide, e-mail faux, consentement dans le futur…). */
    private function nettoyerFamille(array $d, bool $creation): array {
        // Longueur maximale de chaque texte (identique aux colonnes de la base).
        $max = ['nom' => 120, 'adresse' => 255, 'commune' => 120, 'territoire' => 120, 'telephone' => 40, 'email' => 160, 'situation' => 20000, 'orientation' => 120];
        $out = [];
        // Liste blanche : on parcourt les colonnes AUTORISÉES, pas ce qu'envoie le navigateur.
        // Un champ inattendu (« statut », « referent »…) est donc simplement ignoré.
        foreach (Store::CHAMPS_FAMILLE as $col) {
            $cle = Store::camel($col);
            // Champ absent de la requête : on n'y touche pas (modification partielle).
            if (!array_key_exists($cle, $d)) { continue; }
            // match (PHP 8) : comme switch, mais renvoie une valeur et compare strictement (===).
            $out[$col] = match ($col) {
                'date_entree' => $this->date($d[$cle], 'Début de l\'accompagnement'),
                'consentement' => $this->date($d[$cle], 'Date du consentement'),
                // Besoins : tableau (ou chaîne « a|b ») → chaque besoin nettoyé, un « | » dans un besoin
                // devient « / » (sinon il casserait le découpage), vides et doublons retirés, puis recollé « a|b|c ».
                'besoins' => mb_substr(implode('|', array_unique(array_filter(array_map(fn ($b) => str_replace('|', '/', $this->texte($b, 120)), is_array($d[$cle]) ? $d[$cle] : self::besoins((string)$d[$cle]))))), 0, 1000),
                default => $this->texte($d[$cle], $max[$col]),
            };
        }
        // Règles de cohérence, après nettoyage.
        // Nom obligatoire à la création, et interdit de le vider en modification.
        if (($creation || array_key_exists('nom', $out)) && ($out['nom'] ?? '') === '') { throw new Invalide('Le nom de la famille est obligatoire'); }
        if (($out['email'] ?? '') !== '' && !filter_var($out['email'], FILTER_VALIDATE_EMAIL)) { throw new Invalide('Adresse e-mail invalide'); }
        // Les dates 'AAAA-MM-JJ' se comparent correctement comme des chaînes.
        if (($out['consentement'] ?? '') > date('Y-m-d')) { throw new Invalide('La date du consentement ne peut pas être dans le futur'); }
        return $out;
    }

    /** Même principe que nettoyerFamille, pour un membre. Le prénom est obligatoire.
     *  Lance Invalide (prénom vide, naissance dans le futur, e-mail faux). */
    private function nettoyerMembre(array $d, bool $creation): array {
        $max = ['prenom' => 80, 'nom' => 80, 'lien' => 40, 'telephone' => 40, 'email' => 160, 'etablissement' => 160, 'medecin_traitant' => 160, 'psychologue' => 160, 'autres_suivis' => 5000, 'vigilance' => 5000, 'remarques' => 5000];
        $out = [];
        foreach (Store::CHAMPS_MEMBRE as $col) {
            $cle = Store::camel($col);
            if (!array_key_exists($cle, $d)) { continue; }
            $out[$col] = match ($col) {
                'date_naissance' => $this->date($d[$cle], 'Date de naissance'),
                'ordre' => (int)$d[$cle],   // position d'affichage dans la liste
                default => $this->texte($d[$cle], $max[$col]),
            };
        }
        if (($creation || array_key_exists('prenom', $out)) && ($out['prenom'] ?? '') === '') { throw new Invalide('Le prénom est obligatoire'); }
        if (($out['date_naissance'] ?? '') > date('Y-m-d')) { throw new Invalide('La date de naissance est dans le futur'); }
        if (($out['email'] ?? '') !== '' && !filter_var($out['email'], FILTER_VALIDATE_EMAIL)) { throw new Invalide('Adresse e-mail invalide'); }
        return $out;
    }

    /**
     * Nettoie une note de suivi. Le texte est obligatoire ; date du jour par défaut.
     * Renvoie TOUS les champs (une note est toujours enregistrée en entier).
     * Lance Invalide (note vide, date fausse, membre d'une autre famille, durée hors limites).
     */
    private function nettoyerSuivi(int $familleId, array $d): array {
        // Texte obligatoire.
        $texte = $this->texte($d['texte'] ?? '', 20000);
        if ($texte === '') { throw new Invalide('La note est vide'); }
        $date = $this->date($d['dateSuivi'] ?? '', 'Date') ?: date('Y-m-d');

        // Membre concerné (facultatif). Il doit appartenir à CETTE famille : sinon on pourrait
        // rattacher une note à quelqu'un d'une famille qu'on ne voit pas.
        $membreId = isset($d['membreId']) && $d['membreId'] !== '' && $d['membreId'] !== null ? (int)$d['membreId'] : null;
        if ($membreId !== null) {
            $m = $this->store->membre($membreId);
            if ($m === null || $m['familleId'] !== $familleId) { throw new Invalide('Ce membre n\'appartient pas à la famille'); }
        }

        // Durée en minutes (facultative), entre 0 et 24 h.
        $duree = isset($d['duree']) && $d['duree'] !== '' && $d['duree'] !== null ? (int)$d['duree'] : null;
        if ($duree !== null && ($duree < 0 || $duree > 24 * 60)) { throw new Invalide('Durée invalide'); }

        // `$duree ?: null` : une durée de 0 est enregistrée comme « pas de durée ».
        // `!empty($d['prive'])` : vrai pour true, 1, "1"… ; faux si absent.
        return [
            'membre_id' => $membreId, 'date_suivi' => $date, 'type' => $this->texte($d['type'] ?? '', 80),
            'duree' => $duree ?: null, 'texte' => $texte, 'prive' => !empty($d['prive']),
        ];
    }

    /**
     * Nettoie une action / un rendez-vous. À la création, titre et genre sont toujours remplis ;
     * en modification, seuls les champs envoyés sont gardés.
     * Lance Invalide (titre vide, heure fausse, responsable sans accès, rendez-vous sans date).
     */
    private function nettoyerAction(int $familleId, array $d, bool $creation): array {
        $out = [];
        // Titre (obligatoire).
        if ($creation || array_key_exists('titre', $d)) {
            $out['titre'] = $this->texte($d['titre'] ?? '', 255);
            if ($out['titre'] === '') { throw new Invalide('Décrivez l\'action ou le rendez-vous'); }
        }
        // Genre : 'rdv' ou 'action' ; toute autre valeur devient 'action'.
        if ($creation || array_key_exists('genre', $d)) {
            $out['genre'] = ($d['genre'] ?? 'action') === 'rdv' ? 'rdv' : 'action';
        }
        // Échéance : date valide ou null.
        if (array_key_exists('echeance', $d)) { $out['echeance'] = $this->date($d['echeance'], 'Date') ?: null; }
        // Heure « HH:MM » vérifiée par une expression régulière : 00 à 23 h, 00 à 59 min.
        if (array_key_exists('heure', $d)) {
            $h = trim((string)$d['heure']);
            if ($h !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h)) { throw new Invalide('Heure invalide'); }
            $out['heure'] = $h ?: null;
        }
        // Responsable : doit voir la famille, sinon il recevrait une action qu'il ne peut pas ouvrir.
        if (array_key_exists('responsable', $d)) {
            $r = trim((string)$d['responsable']);
            if ($r !== '' && !$this->peutVoir($r, $familleId)) { throw new Invalide('La personne responsable doit avoir accès à la famille'); }
            $out['responsable'] = $r ?: null;
        }
        // Un rendez-vous sans date n'a pas de sens.
        if (($out['genre'] ?? null) === 'rdv' && empty($out['echeance'])) { throw new Invalide('Un rendez-vous a besoin d\'une date'); }
        return $out;
    }

    /** « téléphone, adresse » : noms des champs réellement changés, pour le journal (sans les valeurs).
     *  $avant a des clés « JavaScript » (dateEntree), $champs des colonnes SQL (date_entree) : d'où Store::camel. */
    private function champsModifies(array $avant, array $champs): string {
        // Libellé lisible de chaque colonne.
        $libelles = [
            'nom' => 'nom', 'adresse' => 'adresse', 'commune' => 'commune', 'territoire' => 'territoire', 'telephone' => 'téléphone',
            'email' => 'e-mail', 'situation' => 'situation', 'date_entree' => 'début', 'orientation' => 'orientation', 'besoins' => 'besoins',
            'consentement' => 'consentement', 'prenom' => 'prénom', 'lien' => 'lien', 'date_naissance' => 'naissance',
            'etablissement' => 'établissement', 'medecin_traitant' => 'médecin traitant', 'psychologue' => 'psychologue',
            'autres_suivis' => 'autres suivis', 'vigilance' => 'points de vigilance', 'remarques' => 'remarques', 'ordre' => 'ordre',
        ];
        $changes = [];
        foreach ($champs as $col => $v) {
            // Comparaison en texte : évite qu'un null et une chaîne vide passent pour un changement.
            if ((string)($avant[Store::camel($col)] ?? '') !== (string)$v) { $changes[] = $libelles[$col] ?? $col; }
        }
        return $changes === [] ? 'aucun changement' : implode(', ', $changes);
    }

    // =====================================================================
    // Notifications (cloche Nextcloud) : « acces » (famille confiée), « action » (action confiée)
    // =====================================================================

    /**
     * Envoie une notification à $cible dans la cloche Nextcloud.
     * On ne stocke ici que des DONNÉES (sujet + paramètres) : la phrase affichée est fabriquée
     * plus tard par lib/Notification/Notifier.php, au moment où la cloche s'affiche.
     * Les méthodes setXxx() renvoient l'objet lui-même, d'où l'enchaînement ->setApp()->setUser()…
     */
    private function notifier(string $cible, string $sujet, int $familleId, array $params): void {
        $n = $this->notifications->createNotification();
        $n->setApp(Application::APP_ID)
            ->setUser($cible)
            ->setDateTime(new \DateTime())
            // L'objet concerné (type + id) : permet à Nextcloud de regrouper ou retirer les notifications d'une famille.
            ->setObject('famille', (string)$familleId)
            ->setSubject($sujet, $params + ['id' => $familleId]);
        $this->notifications->notify($n);
    }
}
