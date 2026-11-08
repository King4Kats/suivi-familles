# 5. Développer

## La boucle de travail

```
  modifier app/…  ──▶  ./scripts/deployer.sh  ──▶  recharger http://localhost:8150  ──▶  ./scripts/tester.sh
                        (dépose l'appli dans                                         (syntaxe + droits sur
                         la démo qui tourne)                                          une installation neuve)
```

1. `docker compose up --build` une fois, et laisser tourner.
2. Modifier les fichiers dans `app/`.
3. `./scripts/deployer.sh` : l'appli est recopiée dans le conteneur `familles-nc`.
4. Recharger la page (Ctrl + F5 si le navigateur garde l'ancien JavaScript en cache).
5. Avant d'envoyer : `./scripts/tester.sh`. GitHub relance les mêmes tests à chaque envoi.

Sous Windows, lancer les scripts dans **Git Bash**.

## Changer la base de données

1. Ajouter une migration dans `app/lib/Migration/`, par exemple
   `Version000300Date20270115.php`. Copier la précédente : elle vérifie `hasTable` / `hasColumn`
   avant d'ajouter, ce qui permet de la relancer sans risque.
2. **Monter `<version>`** dans `app/appinfo/info.xml` : Nextcloud ne lance les migrations que si
   la version augmente.
3. `./scripts/deployer.sh` (il lance `occ upgrade`).

Monter la version sert aussi à vider le cache du navigateur : Nextcloud ajoute `?v=<version>`
aux adresses du JavaScript et du CSS.

## Ajouter une fonction : où écrire quoi

| Je veux… | Fichier |
|---|---|
| une nouvelle adresse d'API | `appinfo/routes.php` + une méthode dans `Controller/ApiController.php` |
| une nouvelle règle (droit, validation) | `Service/FamillesService.php` |
| lire ou écrire en base | `Service/Store.php` (requêtes avec paramètres liés, jamais de SQL collé) |
| un nouvel écran ou bouton | `js/main.js` (`vueXxx()`, puis l'ajouter dans `route()` et `render()`) |
| une bulle d'aide « ? » | l'objet `AIDE` en haut de `js/main.js`, puis `aide('cle')` à côté de l'élément |
| une liste de choix | `Service/Reglages.php` |
| un texte de notification | `Notification/Notifier.php` |

Et toujours : un cas de plus dans `tests/tester-droits.py` si la règle touche aux droits.

## Conventions

- **Tout en français** : noms de variables, commentaires, messages. Le code est commenté
  paragraphe par paragraphe pour qu'un développeur débutant puisse le suivre.
- **Pas d'outil de compilation** : PHP et JavaScript tels quels, aucune dépendance à installer.
- Le contrôleur ne contient **aucune règle** ; le store ne vérifie **aucun droit**.
- Les erreurs « métier » sont des exceptions (`Invalide`, `Interdit`, `NonTrouve`) que le
  contrôleur transforme en 400, 403 ou 404 avec un message lisible.
- Les textes de l'interface parlent aux utilisateurs, pas aux développeurs : pas de jargon.

## Monter de version de Nextcloud

1. Changer la ligne `FROM nextcloud:…` de `docker/Dockerfile` (tag **et** empreinte : `docker pull
   nextcloud:<tag>` puis `docker image inspect --format '{{json .RepoDigests}}' nextcloud:<tag>`).
2. Ajuster `<nextcloud min-version max-version>` dans `app/appinfo/info.xml`.
3. `./scripts/tester.sh`.

## Publier une version

1. Monter `<version>` dans `info.xml` et ajouter une entrée dans `CHANGELOG.md`.
2. Commit, puis une étiquette : `git tag v0.3.0 && git push --tags`.
3. Archive à installer à la main sur un Nextcloud : le dossier `app/` renommé `suivi_familles`,
   déposé dans `custom_apps/`, puis `occ app:enable suivi_familles`.
