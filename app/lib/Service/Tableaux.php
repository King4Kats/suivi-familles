<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Service;

/**
 * L'ÉCRAN D'ACCUEIL :
 *  - accueil() : « ma journée » pour chacun (mes actions et rendez-vous, mes familles) ;
 *    ne montre QUE des familles que la personne peut voir.
 *
 * Pas de vérification de droits ici : on part de FamillesService::liste(), qui ne renvoie
 * déjà que les familles visibles. Tout le reste en découle.
 */
class Tableaux {
    // Injection de dépendances : Nextcloud fournit Store et FamillesService (promotion de propriétés).
    public function __construct(private Store $store, private FamillesService $familles) {
    }

    /**
     * Données de l'accueil pour $uid. Qui : tout compte connecté.
     * Renvoie : direction (bool), mesActions, mesFamilles, chiffres ;
     * + pour la direction seulement : sansEquipe, sansReferent, sansConsentement.
     */
    public function accueil(string $uid): array {
        // Les familles visibles, et un index [id => famille] pour les retrouver vite.
        $direction = $this->familles->estDirection($uid);
        $liste = $this->familles->liste($uid);
        $parId = [];
        foreach ($liste as $f) { $parId[$f['id']] = $f; }
        // fn ($f) => … : fonction fléchée qui renvoie true pour garder la famille.
        $actives = array_filter($liste, fn ($f) => $f['statut'] === 'actif');

        // Mes actions : celles qui me sont confiées, dans les familles que je vois encore.
        $aujourdhui = date('Y-m-d');
        $dans7 = date('Y-m-d', strtotime('+7 days'));
        $mesActions = [];
        foreach ($this->store->actionsOuvertes(array_keys($parId)) as $a) {
            if ($a['responsable'] !== $uid) { continue; }
            // Rangement par urgence. Les dates 'AAAA-MM-JJ' se comparent comme du texte.
            // Lecture des ternaires imbriqués : pas de date → plus_tard ; passée → retard ;
            // aujourd'hui → aujourdhui ; d'ici 7 jours → semaine ; sinon → plus_tard.
            $e = $a['echeance'];
            $quand = !$e ? 'plus_tard' : ($e < $aujourdhui ? 'retard' : ($e === $aujourdhui ? 'aujourdhui' : ($e <= $dans7 ? 'semaine' : 'plus_tard')));
            $mesActions[] = $a + ['famille' => $parId[$a['familleId']]['nom'], 'quand' => $quand];
        }

        // Familles « à moi » : référent·e, ou (intervenant) toutes celles confiées.
        $miennes = array_values(array_filter($actives, fn ($f) => $f['referent'] === $uid || !$direction));
        // $resume est une closure rangée dans une variable : on la réutilise plusieurs fois plus bas.
        $resume = fn ($f) => ['id' => $f['id'], 'nom' => $f['nom'], 'dernierSuivi' => $f['dernierSuivi'], 'commune' => $f['commune'], 'prochaine' => $f['prochaine']];

        $out = [
            'direction' => $direction,
            'mesActions' => $mesActions,
            'mesFamilles' => array_map($resume, $miennes),
            'chiffres' => [
                'actives' => count($actives),
                'actionsRetard' => count(array_filter($mesActions, fn ($a) => $a['quand'] === 'retard')),
            ],
        ];
        if ($direction) {
            // Points d'attention de la direction.
            // array_values après array_filter : renumérote 0, 1, 2… pour obtenir une liste JSON.
            $out['sansEquipe'] = array_values(array_map($resume, array_filter($actives, fn ($f) => !$f['equipe'])));
            $out['sansReferent'] = array_values(array_map($resume, array_filter($actives, fn ($f) => !$f['referent'])));
            $out['sansConsentement'] = array_values(array_map($resume, array_filter($actives, fn ($f) => $f['sansConsentement'])));
        }
        return $out;
    }
}
