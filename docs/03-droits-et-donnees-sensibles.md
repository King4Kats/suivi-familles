# 3. Droits et données sensibles

Les fiches contiennent des données personnelles et souvent des données de santé.
Le cloisonnement est donc le cœur de l'appli.

## Deux profils

```
                         ┌───────────────────────────────┐
                         │          DIRECTION            │
                         │ admins Nextcloud + groupes de │
                         │ « groupes_direction »         │
                         │ → voit TOUTES les familles    │
                         └───────────────┬───────────────┘
                                         │ ouvre l'accès (onglet Équipe)
                 ┌───────────────────────┼───────────────────────┐
                 ▼                       ▼                       ▼
        Famille Moreau            Famille Benali           Famille Garnier
        Paul (psychologue)        Marie (médecin,          (personne : direction
        Thomas (référent,          peut modifier)           seulement)
         peut modifier)

  Paul ne voit QUE Moreau. Marie ne voit QUE Benali. Garnier n'apparaît chez personne.
```

| | Direction | Intervenant « peut modifier » | Intervenant |
|---|---|---|---|
| Familles visibles | toutes | les siennes | les siennes |
| Lire la fiche, noter une intervention, gérer les actions | ✔ | ✔ | ✔ |
| Modifier coordonnées, membres, objectifs | ✔ | ✔ | ✘ |
| Lire les notes privées des autres | ✔ | ✘ | ✘ |
| Accès, référent·e, clôture, suppression | ✔ | ✘ | ✘ |
| Journal des accès | ✔ | ✘ | ✘ |

La direction se règle par une commande :

```bash
occ config:app:set suivi_familles groupes_direction --value="direction,coordination"
```

## Les règles, et où elles sont

Toutes dans `app/lib/Service/FamillesService.php` :

- **Une famille inaccessible répond 404**, exactement comme une famille qui n'existe pas
  (`exigerVoir`). On ne révèle même pas son existence.
- **Notes privées** : visibles seulement par leur auteur et la direction.
- Une action ne peut être **confiée** qu'à une personne qui voit la famille.
- Le ou la **référent·e** fait partie de l'équipe ; retirer son accès lui retire le rôle.
- Les **dates** sont vérifiées (une naissance ou un consentement ne peut pas être dans le futur).

## Le journal

Chaque consultation (au plus une par quart d'heure et par personne), impression et
modification est inscrite dans `sf_journal`. Personne ne peut le modifier, pas même la direction.
Il se lit dans l'onglet Journal d'une fiche et dans « Journal des accès » (direction).

## Vérifié automatiquement

`tests/tester-droits.py` se connecte successivement comme Claire (direction), Paul et Marie,
et vérifie une cinquantaine de cas : qui voit quoi, qui peut modifier quoi, les 404, les notes
privées, les notifications… Il tourne à chaque envoi sur GitHub (voir [Développer](05-developper.md)).

## Ce qui reste à la charge de la structure

L'appli aide, mais ne remplace pas les obligations légales :

- **RGPD** : registre des traitements, information et consentement des familles (la fiche a un
  champ « consentement recueilli le »), durée de conservation, droit d'accès (imprimer la fiche)
  et d'effacement (supprimer la fiche).
- **Données de santé** en France : l'hébergement doit être certifié **HDS**. Vérifiez votre
  hébergeur avant d'y mettre de vraies données.
- Sécurité du Nextcloud lui-même : HTTPS, mises à jour, double authentification conseillée.
