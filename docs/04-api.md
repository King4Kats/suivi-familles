# 4. API

Toutes les adresses commencent par `/index.php/apps/suivi_familles/api`. Elles répondent en JSON
et sont déclarées dans `app/appinfo/routes.php`.

Depuis la page de l'appli, `js/main.js` les appelle avec le jeton CSRF de Nextcloud.
Depuis un script, on peut utiliser l'authentification « Basic » et l'en-tête `OCS-APIRequest: true`
(c'est ce que fait `tests/tester-droits.py`) :

```bash
curl -u paul:familles2026 -H "OCS-APIRequest: true" \
  http://localhost:8150/index.php/apps/suivi_familles/api/familles
```

Codes d'erreur : **400** donnée invalide (message en français dans `message`), **403** action
réservée (ex. direction), **404** introuvable *ou* inaccessible (volontairement indiscernables).

## Adresses

| Verbe | Adresse | Rôle | Qui |
|---|---|---|---|
| GET | `/accueil` | mes actions et rendez-vous, mes familles, points d'attention | tous |
| GET | `/reglages` | listes de choix des formulaires | tous |
| GET | `/familles` | les familles que je peux voir | tous |
| POST | `/familles` | créer une famille | direction |
| GET | `/familles/{id}` | la fiche complète | équipe |
| PUT | `/familles/{id}` | modifier la fiche | peut modifier |
| DELETE | `/familles/{id}` | supprimer définitivement | direction |
| PUT | `/familles/{id}/statut` | clore (`dateFin`, `motif`) ou rouvrir | direction |
| PUT | `/familles/{id}/referent` | désigner le ou la référent·e (`userId`) | direction |
| POST | `/familles/{id}/impression` | noter une impression au journal | équipe |
| POST | `/familles/{id}/membres` | ajouter un membre | peut modifier |
| PUT · DELETE | `/membres/{id}` | modifier / retirer un membre | peut modifier |
| POST | `/familles/{id}/suivis` | noter une intervention | équipe |
| PUT · DELETE | `/suivis/{id}` | modifier / supprimer **sa** note | auteur, direction |
| POST | `/familles/{id}/actions` | ajouter une action ou un rendez-vous | équipe |
| PUT · DELETE | `/actions/{id}` | modifier, cocher « fait », supprimer | équipe |
| POST | `/familles/{id}/objectifs` | ajouter un objectif | peut modifier |
| PUT · DELETE | `/objectifs/{id}` | changer le statut, retirer | peut modifier |
| POST | `/familles/{id}/acces` | ouvrir l'accès à un compte | direction |
| PUT · DELETE | `/acces/{id}` | changer la fonction / « peut modifier », retirer | direction |
| GET | `/utilisateurs?q=` | chercher un compte Nextcloud | direction |
| GET | `/journal[?famille=ID]` | journal des accès | direction |

« équipe » = les personnes qui ont accès à la famille, plus la direction.

## Champs

Les noms sont en *camelCase* dans le JSON, en *snake_case* dans la base.

**Famille** : `nom` (obligatoire), `adresse`, `commune`, `territoire`, `telephone`, `email`,
`situation`, `dateEntree`, `orientation`, `besoins` (liste), `consentement` (date).

**Membre** : `prenom` (obligatoire), `nom`, `lien`, `dateNaissance`, `telephone`, `email`,
`etablissement`, `medecinTraitant`, `psychologue`, `autresSuivis`, `vigilance`, `remarques`.

**Intervention** : `texte` (obligatoire), `dateSuivi`, `type`, `duree` (minutes), `membreId`, `prive`.

**Action** : `titre` (obligatoire), `genre` (`action` ou `rdv`, un rendez-vous exige une date),
`echeance`, `heure` (HH:MM), `responsable`, `fait`.

**Objectif** : `titre`, `statut` (`en_cours`, `atteint`, `abandonne`).

Les dates sont au format `AAAA-MM-JJ`.
