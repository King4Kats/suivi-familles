#!/bin/sh
# SPDX-FileCopyrightText: 2026 Flavien Auvray
# SPDX-License-Identifier: AGPL-3.0-or-later
# Exécuté à CHAQUE démarrage du conteneur (par l'image Nextcloud officielle, en www-data,
# une fois Nextcloud installé).
#
# Rôle : copier l'appli depuis l'image (/opt/apps-familles) vers le dossier des applis de
# Nextcloud (/var/www/html/custom_apps), SEULEMENT si la version de l'image est plus récente.
# Ainsi, une version de travail déposée avec scripts/deployer.sh n'est pas écrasée par un
# simple redémarrage.
set -eu

SOURCE=/opt/apps-familles/suivi_familles
CIBLE=/var/www/html/custom_apps/suivi_familles
OCC="php /var/www/html/occ"

# Lit <version>…</version> dans un info.xml (vide si l'appli n'est pas là).
version_de() {
  [ -f "$1/appinfo/info.xml" ] || { echo ""; return; }
  sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$1/appinfo/info.xml" | head -n 1
}

image=$(version_de "$SOURCE")
installee=$(version_de "$CIBLE")
# version_compare de PHP renvoie 1 si la version de l'image est plus récente.
if [ -z "$installee" ] || [ "$(php -r "echo (int) version_compare('$image', '$installee', '>');")" = "1" ]; then
  echo "[familles] installation de la version $image (installée : ${installee:-aucune})"
  rm -rf "$CIBLE"
  cp -R "$SOURCE" "$CIBLE"
fi
$OCC app:enable suivi_familles >/dev/null

# Migrations de base de données si la version a changé. Pas de « || true » : si une migration
# échoue, on préfère que le conteneur s'arrête avec un message clair.
if ! sortie=$($OCC upgrade 2>&1); then
  echo "[familles] ÉCHEC de « occ upgrade » : le conteneur s'arrête."
  echo "$sortie"
  exit 1
fi
