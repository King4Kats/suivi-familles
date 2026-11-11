# Journal des versions

## 0.3.0 — 2026-11-11

- **Chiffrement des champs sensibles en base** : situation de la famille ; médecin traitant,
  psychologue, autres suivis, points de vigilance et remarques des membres ; texte des notes de suivi.
  La migration chiffre les données existantes. Voir docs/03-droits-et-donnees-sensibles.md.
- Test supplémentaire : lecture de la base brute pour vérifier qu'aucun de ces champs n'est en clair.

## 0.2.1 — 2026-11-08

- Retrait du bilan d'activité, de l'alerte « à relancer » et de l'écran Réglages (jugés inutiles).
  Les listes de choix se règlent désormais par `occ` (voir docs/02-architecture.md).
- Première publication open source (AGPL-3.0-or-later), avec démo Docker, tests et documentation.

## 0.2.0 — 2026-11-08

- Accueil personnel : mes actions et rendez-vous, mes familles, points d'attention de la direction.
- Liste des familles filtrable et triable.
- Fiche à onglets : Synthèse, Membres, Suivi, À faire, Équipe, Journal.
- Actions et rendez-vous confiés et notifiés ; objectifs ; référent·e ; clôture avec motif.
- Impression / PDF de la fiche, tracée au journal.
- Bulles d'aide « ? », guide d'utilisation intégré.

## 0.1.0 — 2026-11-08

- Première version : fiches, membres, notes de suivi, accès cloisonné, journal.
