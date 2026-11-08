<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

// strict_types : pas de conversion de type silencieuse (voir FamillesService.php).
declare(strict_types=1);

namespace OCA\SuiviFamilles\Service;

// IQueryBuilder : le « constructeur de requêtes » de Nextcloud. On n'écrit jamais de SQL à la main :
// on assemble la requête par méthodes (select, from, where…), et Nextcloud la traduit pour la base
// utilisée (MySQL/MariaDB, PostgreSQL, SQLite, Oracle).
use OCP\DB\QueryBuilder\IQueryBuilder;
// IDBConnection : la connexion à la base, fournie par Nextcloud.
use OCP\IDBConnection;

/**
 * ACCÈS À LA BASE (les 7 tables sf_*). Uniquement des lectures / écritures, AUCUNE règle :
 * « qui a le droit de faire quoi » est décidé dans FamillesService, avant d'arriver ici.
 *
 * Les lignes sont rendues en tableaux PHP avec des clés « à la JavaScript »
 * (date_naissance → dateNaissance), prêtes à partir vers le navigateur.
 *
 * SÉCURITÉ : toute valeur venue de l'extérieur passe par createNamedParameter().
 * C'est un « paramètre lié » : la valeur est envoyée à la base À CÔTÉ de la requête, jamais collée
 * dedans. Un nom de famille comme « O'Brien'); DROP TABLE… » reste donc un simple texte :
 * c'est la protection contre l'injection SQL.
 * Les noms de tables et de colonnes, eux, sont toujours écrits dans le code (jamais reçus du navigateur).
 *
 * Nextcloud ajoute tout seul le préfixe « oc_ » : on écrit 'sf_familles', la table s'appelle oc_sf_familles.
 */
class Store {
    // Colonnes modifiables par formulaire (tout le reste est géré par le serveur).
    // (referent, statut, date_fin et motif_fin : décisions de la direction, traitées à part.)
    // `public const` : constante de classe, lue ailleurs par Store::CHAMPS_FAMILLE (liste blanche de nettoyerFamille).
    public const CHAMPS_FAMILLE = ['nom', 'adresse', 'commune', 'territoire', 'telephone', 'email', 'situation', 'date_entree', 'orientation', 'besoins', 'consentement'];
    public const CHAMPS_MEMBRE = ['prenom', 'nom', 'lien', 'date_naissance', 'telephone', 'email', 'etablissement', 'medecin_traitant', 'psychologue', 'autres_suivis', 'vigilance', 'remarques', 'ordre'];

    // Injection de dépendances + promotion de propriété : Nextcloud fournit la connexion, rangée dans $this->db.
    public function __construct(private IDBConnection $db) {
    }

    // ---------- outils ----------

    /** 'date_naissance' → 'dateNaissance'
     *  Étapes : « date naissance » → « Date Naissance » → « DateNaissance » → « dateNaissance ». */
    public static function camel(string $s): string {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $s))));
    }

    /** Ligne SQL → tableau JS (ids et compteurs en nombres, booléens en vrais booléens).
     *  Pourquoi : selon la base, une colonne numérique peut revenir en texte ("12") et un booléen en "1"/"0".
     *  $entiers / $booleens : noms des colonnes à convertir. */
    private function versJs(array $r, array $entiers = [], array $booleens = []): array {
        $out = [];
        foreach ($r as $k => $v) {
            // Un entier NULL reste null (ex. membre_id d'une note sans membre).
            if (in_array($k, $entiers, true)) { $v = $v === null ? null : (int)$v; }
            elseif (in_array($k, $booleens, true)) { $v = (bool)$v; }
            $out[self::camel($k)] = $v;
        }
        return $out;
    }

    /** Type de paramètre SQL d'une valeur (PARAM_BOOL est une chaîne chez Nextcloud, d'où int|string).
     *  On dit à la base quel type elle reçoit : PARAM_NULL, PARAM_BOOL, PARAM_INT ou PARAM_STR (texte).
     *  `int|string` = « type union » : la fonction renvoie un entier OU une chaîne. */
    private function type(mixed $v): int|string {
        return $v === null ? IQueryBuilder::PARAM_NULL : (is_bool($v) ? IQueryBuilder::PARAM_BOOL : (is_int($v) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
    }

    /** INSERT INTO $table (col1, col2…) VALUES (…). $valeurs = [colonne => valeur].
     *  Renvoie l'id de la nouvelle ligne (colonne auto-incrémentée). */
    private function inserer(string $table, array $valeurs): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert($table);
        // Chaque valeur passe par un paramètre lié, avec son type.
        foreach ($valeurs as $col => $v) {
            $qb->setValue($col, $qb->createNamedParameter($v, $this->type($v)));
        }
        // executeStatement() : pour les requêtes qui ÉCRIVENT (insert, update, delete).
        $qb->executeStatement();
        return $qb->getLastInsertId();
    }

    /** UPDATE $table SET col = valeur… WHERE id = $id. Ne fait rien si $valeurs est vide. */
    private function mettreAJour(string $table, int $id, array $valeurs): void {
        // Un UPDATE sans aucun SET serait une requête invalide : on sort tout de suite.
        if ($valeurs === []) { return; }
        $qb = $this->db->getQueryBuilder();
        $qb->update($table);
        foreach ($valeurs as $col => $v) {
            $qb->set($col, $qb->createNamedParameter($v, $this->type($v)));
        }
        // Le WHERE est indispensable : sans lui, TOUTES les lignes de la table seraient modifiées.
        $qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeStatement();
    }

    /** DELETE FROM $table WHERE $col = $valeur (ex. toutes les lignes d'une famille). */
    private function supprimerOu(string $table, string $col, int $valeur): void {
        $qb = $this->db->getQueryBuilder();
        // $qb->expr()->eq(a, b) fabrique la condition « a = b ».
        $qb->delete($table)->where($qb->expr()->eq($col, $qb->createNamedParameter($valeur, IQueryBuilder::PARAM_INT)))->executeStatement();
    }

    /** SELECT * FROM $table WHERE $col = $valeur ORDER BY $tri, id. Renvoie les lignes brutes (colonnes SQL). */
    private function lignes(string $table, string $col, int $valeur, string $tri = 'id'): array {
        $qb = $this->db->getQueryBuilder();
        // addOrderBy('id') : 2e critère de tri, pour un ordre stable quand $tri est à égalité.
        $qb->select('*')->from($table)->where($qb->expr()->eq($col, $qb->createNamedParameter($valeur, IQueryBuilder::PARAM_INT)))->orderBy($tri)->addOrderBy('id');
        // executeQuery() : pour les requêtes qui LISENT. fetchAll() récupère toutes les lignes.
        return $qb->executeQuery()->fetchAll();
    }

    /** Une ligne par son id, ou null si elle n'existe pas. */
    private function ligne(string $table, int $id): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($table)->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        // fetch() renvoie la 1re ligne, ou false s'il n'y en a pas ; `?:` transforme ce false en null.
        $r = $qb->executeQuery()->fetch();
        return $r ?: null;
    }

    // ---------- familles ----------

    /** Toutes les familles, ou seulement celles dont l'id est dans $ids. */
    public function familles(?array $ids = null): array {
        // Liste d'ids vide (intervenant sans aucune famille) : rien à chercher.
        // Et « WHERE id IN () » serait du SQL invalide.
        if ($ids !== null && $ids === []) { return []; }
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('sf_familles')->orderBy('nom');
        if ($ids !== null) {
            // WHERE id IN (…) : PARAM_INT_ARRAY lie TOUTE une liste d'entiers d'un coup.
            // array_map('intval', …) force chaque id en entier, par précaution.
            $qb->where($qb->expr()->in('id', $qb->createNamedParameter(array_map('intval', $ids), IQueryBuilder::PARAM_INT_ARRAY)));
        }
        return array_map(fn ($r) => $this->versJs($r, ['id', 'created_at', 'updated_at']), $qb->executeQuery()->fetchAll());
    }

    /** Une famille par son id, ou null. */
    public function famille(int $id): ?array {
        $r = $this->ligne('sf_familles', $id);
        return $r ? $this->versJs($r, ['id', 'created_at', 'updated_at']) : null;
    }

    /** Crée la famille ; $par = uid de la personne qui la crée. Renvoie son id.
     *  `+` sur des tableaux : ajoute les clés techniques (created_by, created_at…) aux champs du formulaire. */
    public function creerFamille(array $champs, string $par): int {
        $t = time();   // horodatage Unix (secondes depuis 1970)
        return $this->inserer('sf_familles', $champs + ['created_by' => $par, 'created_at' => $t, 'updated_at' => $t]);
    }

    /** Pas de filtrage des colonnes ici : FamillesService a déjà décidé ce qui est permis. */
    public function modifierFamille(int $id, array $champs): void {
        $this->mettreAJour('sf_familles', $id, $champs + ['updated_at' => time()]);
    }

    /** Supprime la famille ET tout ce qui en dépend (membres, accès, suivis, actions, objectifs). Le journal reste.
     *  Les tables n'ont pas de clés étrangères « en cascade » : on supprime donc chaque table à la main,
     *  les dépendances d'abord, la famille en dernier. */
    public function supprimerFamille(int $id): void {
        foreach (['sf_membres', 'sf_acces', 'sf_suivis', 'sf_actions', 'sf_objectifs'] as $table) { $this->supprimerOu($table, 'famille_id', $id); }
        $this->supprimerOu('sf_familles', 'id', $id);
    }

    /** Met seulement à jour la date de modification de la famille (après un changement de membre). */
    public function toucherFamille(int $id): void {
        $this->mettreAJour('sf_familles', $id, ['updated_at' => time()]);
    }

    /** Nombre de membres par famille : [familleId => n].
     *  SQL : SELECT famille_id, COUNT(id) AS n FROM sf_membres GROUP BY famille_id. */
    public function nbMembres(): array {
        $qb = $this->db->getQueryBuilder();
        // $qb->func()->count() : la fonction SQL COUNT, écrite de façon portable ; selectAlias lui donne le nom « n ».
        $qb->select('famille_id')->selectAlias($qb->func()->count('id'), 'n')->from('sf_membres')->groupBy('famille_id');
        $out = [];
        foreach ($qb->executeQuery()->fetchAll() as $r) { $out[(int)$r['famille_id']] = (int)$r['n']; }
        return $out;
    }

    /** Date de la dernière intervention par famille : [familleId => 'AAAA-MM-JJ'].
     *  Les notes datées dans le futur ne comptent pas (ce n'est pas encore un contact).
     *  SQL : SELECT famille_id, MAX(date_suivi) AS d … WHERE date_suivi <= aujourd'hui GROUP BY famille_id.
     *  MAX fonctionne sur ces dates texte parce que le format 'AAAA-MM-JJ' se trie comme une date. */
    public function derniersSuivis(): array {
        $qb = $this->db->getQueryBuilder();
        // lte = « less than or equal » (<=). createNamedParameter sans type = PARAM_STR (texte) par défaut.
        $qb->select('famille_id')->selectAlias($qb->func()->max('date_suivi'), 'd')->from('sf_suivis')
            ->where($qb->expr()->lte('date_suivi', $qb->createNamedParameter(date('Y-m-d'))))->groupBy('famille_id');
        $out = [];
        foreach ($qb->executeQuery()->fetchAll() as $r) { $out[(int)$r['famille_id']] = $r['d']; }
        return $out;
    }

    // ---------- membres ----------

    /** Membres d'une famille, dans l'ordre d'affichage (colonne « ordre »). */
    public function membres(int $familleId): array {
        return array_map(fn ($r) => $this->versJs($r, ['id', 'famille_id', 'ordre']), $this->lignes('sf_membres', 'famille_id', $familleId, 'ordre'));
    }

    /** Un membre par son id, ou null. */
    public function membre(int $id): ?array {
        $r = $this->ligne('sf_membres', $id);
        return $r ? $this->versJs($r, ['id', 'famille_id', 'ordre']) : null;
    }

    /** Crée un membre dans la famille. Renvoie son id. */
    public function creerMembre(int $familleId, array $champs): int {
        return $this->inserer('sf_membres', $champs + ['famille_id' => $familleId]);
    }

    public function modifierMembre(int $id, array $champs): void {
        $this->mettreAJour('sf_membres', $id, $champs);
    }

    /** Supprime le membre ; ses notes de suivi restent, rattachées à la famille seulement. */
    public function supprimerMembre(int $id): void {
        // 1) Détacher les notes : UPDATE sf_suivis SET membre_id = NULL WHERE membre_id = $id.
        $qb = $this->db->getQueryBuilder();
        $qb->update('sf_suivis')->set('membre_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
            ->where($qb->expr()->eq('membre_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeStatement();
        // 2) Supprimer le membre lui-même.
        $this->supprimerOu('sf_membres', 'id', $id);
    }

    // ---------- accès (le cloisonnement) ----------
    // Une ligne dans sf_acces = « tel compte voit telle famille ». Pas de ligne = pas d'accès.

    /** Conversion d'une ligne d'accès (peut_modifier en vrai booléen). */
    private function accesJs(array $r): array {
        return $this->versJs($r, ['id', 'famille_id', 'created_at'], ['peut_modifier']);
    }

    /** L'équipe d'une famille : toutes ses lignes d'accès. */
    public function accesFamille(int $familleId): array {
        return array_map(fn ($r) => $this->accesJs($r), $this->lignes('sf_acces', 'famille_id', $familleId));
    }

    /** Tous les accès d'une personne : [familleId => ligne d'accès].
     *  Indexé par famille pour répondre vite à « a-t-il accès à la famille 12 ? ». */
    public function accesPersonne(string $uid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('sf_acces')->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)));
        $out = [];
        foreach ($qb->executeQuery()->fetchAll() as $r) { $out[(int)$r['famille_id']] = $this->accesJs($r); }
        return $out;
    }

    /** Tous les accès, toutes familles : [familleId => [lignes]] (vue direction).
     *  `$out[x][] = …` : ajoute un élément à la liste de la famille x (créée au besoin). */
    public function accesTous(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('sf_acces')->orderBy('id');
        $out = [];
        foreach ($qb->executeQuery()->fetchAll() as $r) { $out[(int)$r['famille_id']][] = $this->accesJs($r); }
        return $out;
    }

    /** Une ligne d'accès par son id, ou null. */
    public function acces(int $id): ?array {
        $r = $this->ligne('sf_acces', $id);
        return $r ? $this->accesJs($r) : null;
    }

    /** L'accès de $uid à UNE famille, ou null s'il n'en a pas. C'est la question centrale du cloisonnement. */
    public function accesDe(int $familleId, string $uid): ?array {
        return $this->accesPersonne($uid)[$familleId] ?? null;
    }

    /** Ouvre la famille à $uid. $par = la personne de la direction qui l'a fait. Renvoie l'id de l'accès.
     *  Un index unique (famille_id, user_id) en base empêche aussi les doublons. */
    public function ajouterAcces(int $familleId, string $uid, string $fonction, bool $peutModifier, string $par): int {
        return $this->inserer('sf_acces', [
            'famille_id' => $familleId, 'user_id' => $uid, 'fonction' => $fonction,
            'peut_modifier' => $peutModifier, 'created_by' => $par, 'created_at' => time(),
        ]);
    }

    public function modifierAcces(int $id, string $fonction, bool $peutModifier): void {
        $this->mettreAJour('sf_acces', $id, ['fonction' => $fonction, 'peut_modifier' => $peutModifier]);
    }

    /** Supprimer la ligne d'accès = la personne ne voit plus la famille. */
    public function retirerAcces(int $id): void {
        $this->supprimerOu('sf_acces', 'id', $id);
    }

    // ---------- suivis (interventions) ----------

    /** Conversion d'une note (prive en vrai booléen). */
    private function suiviJs(array $r): array {
        return $this->versJs($r, ['id', 'famille_id', 'membre_id', 'duree', 'created_at', 'updated_at'], ['prive']);
    }

    /** Notes d'une famille, la plus récente d'abord.
     *  Toutes les notes, privées comprises : c'est FamillesService::fiche qui retire celles à cacher. */
    public function suivis(int $familleId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('sf_suivis')
            ->where($qb->expr()->eq('famille_id', $qb->createNamedParameter($familleId, IQueryBuilder::PARAM_INT)))
            ->orderBy('date_suivi', 'DESC')->addOrderBy('id', 'DESC');
        return array_map(fn ($r) => $this->suiviJs($r), $qb->executeQuery()->fetchAll());
    }

    /** Une note par son id, ou null. */
    public function suivi(int $id): ?array {
        $r = $this->ligne('sf_suivis', $id);
        return $r ? $this->suiviJs($r) : null;
    }

    /** $champs : membre_id, date_suivi, type, duree, texte, prive (déjà vérifiés). Renvoie l'id de la note. */
    public function creerSuivi(int $familleId, string $auteur, array $champs): int {
        $t = time();
        return $this->inserer('sf_suivis', $champs + ['famille_id' => $familleId, 'auteur' => $auteur, 'created_at' => $t, 'updated_at' => $t]);
    }

    public function modifierSuivi(int $id, array $champs): void {
        $this->mettreAJour('sf_suivis', $id, $champs + ['updated_at' => time()]);
    }

    public function supprimerSuivi(int $id): void {
        $this->supprimerOu('sf_suivis', 'id', $id);
    }

    // ---------- actions (à faire, rendez-vous) ----------

    /** Conversion d'une action (fait en vrai booléen). */
    private function actionJs(array $r): array {
        return $this->versJs($r, ['id', 'famille_id', 'fait_le', 'created_at'], ['fait']);
    }

    /** Actions d'une famille, dans l'ordre d'affichage (voir trierActions). */
    public function actions(int $familleId): array {
        return $this->trierActions(array_map(fn ($r) => $this->actionJs($r), $this->lignes('sf_actions', 'famille_id', $familleId)));
    }

    /** Actions pas encore faites, de toutes les familles ($familleIds = null) ou de celles listées. */
    public function actionsOuvertes(?array $familleIds = null): array {
        if ($familleIds !== null && $familleIds === []) { return []; }
        $qb = $this->db->getQueryBuilder();
        // « pas faite » = fait IS NULL OU fait = false. orX() assemble des conditions avec OR.
        $qb->select('*')->from('sf_actions')
            ->where($qb->expr()->orX($qb->expr()->isNull('fait'), $qb->expr()->eq('fait', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))));
        if ($familleIds !== null) {
            // andWhere : AJOUTE une condition (AND). Un 2e where() remplacerait la première !
            $qb->andWhere($qb->expr()->in('famille_id', $qb->createNamedParameter(array_map('intval', $familleIds), IQueryBuilder::PARAM_INT_ARRAY)));
        }
        return $this->trierActions(array_map(fn ($r) => $this->actionJs($r), $qb->executeQuery()->fetchAll()));
    }

    /** Ordre d'affichage : à faire d'abord, par échéance (sans échéance à la fin), puis les faites (récentes d'abord).
     *  usort trie avec une fonction de comparaison qui renvoie négatif (a avant b), 0, ou positif (b avant a).
     *  `<=>` (« vaisseau spatial ») renvoie justement -1, 0 ou 1. */
    private function trierActions(array $liste): array {
        usort($liste, function ($a, $b) {
            // 1) Les « à faire » avant les « faites ».
            if ($a['fait'] !== $b['fait']) { return $a['fait'] ? 1 : -1; }
            // 2) Entre deux faites : la plus récemment cochée d'abord.
            if ($a['fait']) { return ($b['faitLe'] ?? 0) <=> ($a['faitLe'] ?? 0); }
            // 3) Entre deux à faire : par date + heure. Sans date → '9999-99-99' pour passer en dernier.
            $ea = ($a['echeance'] ?: '9999-99-99') . ($a['heure'] ?: '99:99');
            $eb = ($b['echeance'] ?: '9999-99-99') . ($b['heure'] ?: '99:99');
            // À égalité (strcmp renvoie 0), `?:` passe au départage par id.
            return strcmp($ea, $eb) ?: $a['id'] <=> $b['id'];
        });
        return $liste;
    }

    /** Une action par son id, ou null. */
    public function action(int $id): ?array {
        $r = $this->ligne('sf_actions', $id);
        return $r ? $this->actionJs($r) : null;
    }

    /** Crée une action (toujours « pas faite » au départ). Renvoie son id. */
    public function creerAction(int $familleId, array $champs, string $par): int {
        return $this->inserer('sf_actions', $champs + ['famille_id' => $familleId, 'fait' => false, 'created_by' => $par, 'created_at' => time()]);
    }

    public function modifierAction(int $id, array $champs): void {
        $this->mettreAJour('sf_actions', $id, $champs);
    }

    public function supprimerAction(int $id): void {
        $this->supprimerOu('sf_actions', 'id', $id);
    }

    // ---------- objectifs ----------

    private function objectifJs(array $r): array {
        return $this->versJs($r, ['id', 'famille_id', 'created_at', 'updated_at']);
    }

    /** Objectifs d'une famille, dans l'ordre de création. */
    public function objectifs(int $familleId): array {
        return array_map(fn ($r) => $this->objectifJs($r), $this->lignes('sf_objectifs', 'famille_id', $familleId));
    }

    /** Un objectif par son id, ou null. */
    public function objectif(int $id): ?array {
        $r = $this->ligne('sf_objectifs', $id);
        return $r ? $this->objectifJs($r) : null;
    }

    /** Crée un objectif « en cours ». Renvoie son id. */
    public function creerObjectif(int $familleId, string $titre): int {
        $t = time();
        return $this->inserer('sf_objectifs', ['famille_id' => $familleId, 'titre' => $titre, 'statut' => 'en_cours', 'created_at' => $t, 'updated_at' => $t]);
    }

    public function modifierObjectif(int $id, array $champs): void {
        $this->mettreAJour('sf_objectifs', $id, $champs + ['updated_at' => time()]);
    }

    public function supprimerObjectif(int $id): void {
        $this->supprimerOu('sf_objectifs', 'id', $id);
    }

    // ---------- journal ----------

    /** Ajoute une ligne au journal : qui ($uid), quoi ($action, ex. 'consultation'), précisions ($detail).
     *  $familleId peut être null (action qui ne concerne pas une famille).
     *  Le détail est coupé à 255 caractères, la taille de la colonne. */
    public function journaliser(?int $familleId, string $uid, string $action, string $detail = ''): void {
        $this->inserer('sf_journal', [
            'famille_id' => $familleId, 'user_id' => $uid, 'action' => $action,
            'detail' => mb_substr($detail, 0, 255), 'created_at' => time(),
        ]);
    }

    /** Dernière fois que $uid a fait $action sur cette famille (horodatage), ou null.
     *  Sert à ne pas journaliser une consultation à chaque rechargement (voir FamillesService::fiche). */
    public function derniereAction(int $familleId, string $uid, string $action): ?int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->max('created_at'))->from('sf_journal')
            ->where($qb->expr()->eq('famille_id', $qb->createNamedParameter($familleId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)));
        // fetchOne() : la 1re colonne de la 1re ligne (ici le MAX), ou null/false s'il n'y a rien.
        $v = $qb->executeQuery()->fetchOne();
        return $v ? (int)$v : null;
    }

    /** Les $limite dernières lignes du journal (200 par défaut), de toutes les familles ou d'une seule. */
    public function journal(?int $familleId, int $limite = 200): array {
        $qb = $this->db->getQueryBuilder();
        // setMaxResults = LIMIT en SQL.
        $qb->select('*')->from('sf_journal')->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults($limite);
        if ($familleId !== null) {
            $qb->where($qb->expr()->eq('famille_id', $qb->createNamedParameter($familleId, IQueryBuilder::PARAM_INT)));
        }
        return array_map(fn ($r) => $this->versJs($r, ['id', 'famille_id', 'created_at']), $qb->executeQuery()->fetchAll());
    }
}
