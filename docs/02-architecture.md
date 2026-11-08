# 2. Architecture

L'appli est une **appli Nextcloud native** : du PHP côté serveur (le « App Framework » de
Nextcloud) et une page JavaScript « vanilla » côté navigateur. Rien à compiler : on modifie
un fichier, on le dépose, on recharge la page.

## Le chemin d'une requête

Exemple : Paul ouvre la fiche de la famille n° 12.

```
 Navigateur (js/main.js)                       Serveur Nextcloud (PHP)
 ───────────────────────                       ──────────────────────────────────────────────
 #famille/12 dans l'adresse
        │
        ▼
 api('GET', '/familles/12') ──── HTTP ────▶ appinfo/routes.php
   (avec le jeton CSRF                          « /api/familles/{id} » → api#fiche
    de Nextcloud)                                     │
                                                      ▼
                                              Controller/ApiController::fiche(12)
                                                « qui est connecté ? » → $uid = "paul"
                                                      │
                                                      ▼
                                              Service/FamillesService::fiche("paul", 12)
                                                1. exigerVoir : Paul a-t-il une ligne
                                                   dans sf_acces pour la famille 12 ?
                                                   non → exception NonTrouve → 404
                                                2. lit la fiche, ses membres, ses suivis
                                                   (sans les notes privées des autres)
                                                3. note « consultation » au journal
                                                      │
                                                      ▼
                                              Service/Store (requêtes SQL)
                                                      │
                                                      ▼
                                              Base de données (tables oc_sf_*)
        ◀──────────── JSON ───────────────────
 render() redessine la page
```

**Une règle d'or** : le JavaScript ne fait qu'**afficher**. Cacher un bouton ne protège rien.
Chaque droit est vérifié côté serveur, dans `FamillesService`, au début de chaque méthode.

## Qui fait quoi dans le code

```
app/
├── appinfo/
│   ├── info.xml           carte d'identité : nom, version, licence, compatibilité Nextcloud
│   └── routes.php         la liste des adresses de l'API → méthode du contrôleur
├── lib/
│   ├── AppInfo/Application.php     démarrage : déclare le « Notifier »
│   ├── Controller/
│   │   ├── PageController.php      renvoie la page HTML (templates/main.php)
│   │   └── ApiController.php       reçoit les requêtes, renvoie du JSON. AUCUNE règle ici.
│   ├── Service/
│   │   ├── FamillesService.php     ★ LES RÈGLES : droits, validations, journal, notifications
│   │   ├── Tableaux.php            l'écran d'accueil de chacun
│   │   ├── Reglages.php            les listes de choix des formulaires
│   │   ├── Store.php               la base de données, et rien d'autre
│   │   └── Interdit / Invalide / NonTrouve.php   erreurs → codes HTTP 403 / 400 / 404
│   ├── Notification/Notifier.php   le texte des notifications (cloche Nextcloud)
│   └── Migration/                  création et évolution des tables
├── templates/main.php     la page : une simple <div id="sf-app">
├── js/main.js             toute l'interface (écrans, formulaires, bulles d'aide « ? »)
├── css/main.css           l'apparence (couleurs du thème Nextcloud)
└── img/                   icônes de l'appli
```

Les couches ne se parlent que vers le bas : contrôleur → service → store → base.
Un contrôleur ne touche jamais la base ; le store ne vérifie jamais un droit.

## Les données

Six tables, préfixées `oc_sf_` (le préfixe `oc_` est celui de Nextcloud).

```
                      ┌──────────────────────┐
                      │     sf_familles      │  nom, coordonnées, commune, territoire,
                      │                      │  situation, besoins (« a|b »), orientation,
                      │                      │  référent, consentement, statut actif/clos,
                      │                      │  date et motif de fin
                      └──────────┬───────────┘
         ┌───────────────┬───────┴───────┬───────────────┬───────────────┐
         ▼               ▼               ▼               ▼               ▼
  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
  │ sf_membres  │ │  sf_acces   │ │  sf_suivis  │ │ sf_actions  │ │sf_objectifs │
  │ prénom, lien│ │ qui a accès │ │ interventions│ │ à faire et  │ │ objectifs   │
  │ naissance,  │ │ (user_id),  │ │ datées : type│ │ rendez-vous,│ │ en cours /  │
  │ médecin,    │ │ fonction,   │ │ durée, texte,│ │ responsable,│ │ atteint /   │
  │ vigilance…  │ │ peut_modifier│ │ privée ?    │ │ fait ?      │ │ abandonné   │
  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘

  ┌─────────────┐
  │ sf_journal  │  qui (user_id) a fait quoi (action), sur quelle famille, quand.
  └─────────────┘  Jamais modifié. Garde la trace d'une famille supprimée, sans ses données.
```

Les comptes ne sont pas copiés : `user_id`, `auteur`, `responsable`, `referent` contiennent
l'identifiant Nextcloud de la personne. Le nom affiché est demandé à Nextcloud au moment de
l'affichage.

Les tables sont créées par les **migrations** (`lib/Migration/`). Nextcloud les lance quand la
version de `info.xml` augmente. Elles vérifient ce qui existe déjà (`hasTable`, `hasColumn`) :
on peut les relancer sans risque.

## Les listes de choix

Les menus déroulants (territoires, types d'intervention, besoins, orientations, motifs de fin,
fonctions, liens familiaux) ont des valeurs par défaut dans `app/lib/Service/Reglages.php`.
Chaque structure remplace celles qu'elle veut par **une commande**, sans toucher au code :

```bash
occ config:app:set suivi_familles listes --value='{
  "territoires": ["Nord", "Sud", "Hors territoire"],
  "types": ["Entretien", "Visite à domicile", "Appel", "Atelier"]
}'
```

Clés possibles : `types`, `besoins`, `territoires`, `orientations`, `motifs`, `fonctions`, `liens`.
Une clé absente garde sa liste par défaut. Changer une liste ne modifie pas les fiches existantes.

## L'interface

`js/main.js` est une seule page :

```
 location.hash ──▶ route() ──▶ charge les données (api) ──▶ render() ──▶ vueXxx() ──▶ el(…) ──▶ DOM
   #accueil                                                     ▲
   #familles                 un clic, une saisie validée ───────┘
   #famille/12/suivi
   #nouvelle · #journal · #guide
```

- `route()` lit l'adresse après le `#` et choisit l'écran (le bouton « retour » du navigateur
  marche donc tout seul).
- `render()` vide la page et la redessine entièrement à partir de l'état `S`.
- `el('div', {class: …}, [enfants])` fabrique un élément HTML : c'est notre mini-outil à la
  place d'un framework.
- Les bulles d'aide « ? » s'ouvrent **sans** redessiner la page, pour ne pas effacer un
  formulaire en cours. Leurs textes sont rassemblés dans l'objet `AIDE`, en haut du fichier.
