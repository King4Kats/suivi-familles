# 1. Démarrer

## Essayer la démo

Il faut **Docker** avec le plugin **Compose** (`docker compose version` doit répondre).

```bash
git clone https://github.com/King4Kats/suivi-familles.git
cd suivi-familles
docker compose up --build
```

Le premier lancement prend 1 à 3 minutes. C'est prêt quand le terminal affiche
`[familles] ✓ Démo prête`. Ouvrir http://localhost:8150/apps/suivi_familles/.

Mot de passe de tous les comptes : `familles2026`.

| Compte | Rôle | Ce qu'il voit |
|---|---|---|
| `claire` | direction | toutes les familles, l'équipe de chacune, le journal |
| `thomas` | référent de la famille Durand | la famille Durand, peut la modifier |
| `paul` | psychologue | la famille Durand, en lecture + notes de suivi |
| `marie` | médecin | rien : aucune famille ne lui est confiée |
| `admin` | administrateur Nextcloud | comme la direction |

Pour voir le cloisonnement : ouvrez la famille Durand avec `claire`, onglet **Équipe**, et
donnez l'accès à `marie`. Reconnectez-vous en `marie` : la famille apparaît. Retirez l'accès :
elle disparaît, et son adresse répond « introuvable ».

Plus de familles d'exemple (4 familles, plusieurs équipes) :

```bash
python demo/remplir-familles.py
```

Repartir de zéro : `docker compose down -v` puis `docker compose up`.

## Installer sur un vrai Nextcloud

Nextcloud 30 à 34, PHP 8.1 ou plus.

1. Copier le dossier `app/` dans `custom_apps/` du Nextcloud, **renommé `suivi_familles`**.
2. `occ app:enable suivi_familles` (crée les tables).
3. Créer le groupe de la direction (`occ group:add direction`) et y mettre les bonnes personnes.
   Les administrateurs Nextcloud font toujours partie de la direction.
4. Facultatif : adapter les listes de choix (territoires, types d'intervention…), voir
   [Architecture](02-architecture.md#les-listes-de-choix).

⚠ Avant d'y mettre de vraies données, lire [Droits et données sensibles](03-droits-et-donnees-sensibles.md).

## Utiliser l'appli au quotidien

L'appli a son propre **guide d'utilisation** (menu de gauche), et un petit « ? » à côté de
chaque notion. En bref :

```
  Accueil ─────────▶ ce qui m'attend : mes actions et rendez-vous (en retard en rouge)
     │
  Familles ────────▶ liste filtrable (territoire, besoin, référent·e)
     │
  Fiche famille
     ├── Synthèse     situation, objectifs, prochaines étapes, coordonnées, consentement
     ├── Membres      chaque personne, ses soignants, ses points de vigilance
     ├── Suivi        les interventions datées (type, durée, notes privées possibles)
     ├── À faire      actions et rendez-vous confiés à quelqu'un (il est notifié)
     ├── Équipe       qui a accès, avec quels droits ; le ou la référent·e
     └── Journal      qui a consulté, imprimé ou modifié (direction)
```
