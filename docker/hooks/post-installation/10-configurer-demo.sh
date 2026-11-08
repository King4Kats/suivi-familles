#!/bin/sh
# SPDX-FileCopyrightText: 2026 Flavien Auvray
# SPDX-License-Identifier: AGPL-3.0-or-later
# Exécuté UNE SEULE FOIS, juste après la toute première installation de Nextcloud (volume vide).
# Prépare une démo prête à l'emploi : langue française, comptes fictifs, une famille de démo.
set -eu

OCC="php /var/www/html/occ"
MDP="${FAMILLES_MDP:-familles2026}"

echo "[familles] Configuration de la démo…"
$OCC config:system:set default_language --value=fr
$OCC config:system:set default_locale --value=fr_FR
$OCC config:system:set default_timezone --value=Europe/Paris
# La fenêtre « Bienvenue dans Nextcloud » cache l'écran ; selon les versions elle peut ne pas exister.
$OCC app:disable firstrunwizard >/dev/null 2>&1 || true

# L'appli : même script qu'à chaque démarrage.
sh /docker-entrypoint-hooks.d/before-starting/10-installer-appli.sh

# Comptes FICTIFS. « direction » = le groupe qui voit toutes les familles
# (réglable : occ config:app:set suivi_familles groupes_direction --value="direction").
$OCC group:add --display-name "Direction" direction >/dev/null 2>&1 || true
compte() { # identifiant, nom affiché, groupe (facultatif)
  OC_PASS="$MDP" $OCC user:add --password-from-env --display-name "$2" "$1" >/dev/null
  if [ -n "${3:-}" ]; then $OCC group:adduser "$3" "$1" >/dev/null; fi
}
compte claire "Claire Dubois"  direction   # direction : voit toutes les familles
compte thomas "Thomas Petit"               # salarié, référent de la famille de démo
compte paul   "Paul Lefèvre"               # psychologue : seulement les familles confiées
compte marie  "Marie Garnier"              # médecin : aucune famille au départ → ne voit rien

# Exemple de liste de choix personnalisée : les territoires (voir docs/02-architecture.md).
$OCC config:app:set suivi_familles listes --value='{"territoires":["Océan-Marais de Monts","Challans-Gois Communauté","Pays de Saint-Gilles-Croix-de-Vie","Hors territoire"]}' >/dev/null

# Une famille de démo, entièrement inventée, créée au nom de Claire.
php /opt/apps-familles/famille-demo.php claire thomas paul
echo "[familles] ✓ Démo prête : http://localhost:8150/apps/suivi_familles/"
