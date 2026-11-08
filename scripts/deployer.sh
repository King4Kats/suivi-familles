#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Flavien Auvray
# SPDX-License-Identifier: AGPL-3.0-or-later
# Pendant le développement : dépose la version de travail de l'appli dans la démo qui tourne
# (conteneur familles-nc, lancé par « docker compose up »), sans reconstruire l'image.
#
#   ./scripts/deployer.sh
#
# Pensez à monter <version> dans app/appinfo/info.xml quand la base change (nouvelle migration) :
# Nextcloud ne lance les migrations que si la version augmente.
set -euo pipefail
RACINE="$(cd "$(dirname "$0")/.." && pwd)"
export MSYS_NO_PATHCONV=1
CONTENEUR="${CONTENEUR:-familles-nc}"

# On envoie le dossier app/ en flux tar (fonctionne partout, même sous Windows).
docker exec "$CONTENEUR" rm -rf /var/www/html/custom_apps/suivi_familles
tar -C "$RACINE/app" -cf - . | docker exec -i "$CONTENEUR" sh -c \
  'mkdir -p /var/www/html/custom_apps/suivi_familles && tar -xf - -C /var/www/html/custom_apps/suivi_familles && chown -R www-data:www-data /var/www/html/custom_apps/suivi_familles'
docker exec -u www-data "$CONTENEUR" php occ app:enable suivi_familles >/dev/null
docker exec -u www-data "$CONTENEUR" php occ upgrade >/dev/null
echo "✓ Appli déposée dans $CONTENEUR : rechargez la page."
