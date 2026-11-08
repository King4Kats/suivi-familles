# SPDX-FileCopyrightText: 2026 Flavien Auvray
# SPDX-License-Identifier: AGPL-3.0-or-later
# Image de démonstration « tout compris » : Nextcloud 34 + l'appli Familles.
#
# Utilisée par docker-compose.yml (« docker compose up --build ») et par scripts/tester.sh.
# L'appli n'a rien à compiler (PHP + JavaScript sans outil de build) : on la copie telle quelle.
#
# REPRODUCTIBILITÉ : l'image de base est désignée par son tag (pour les humains) ET son
# empreinte @sha256 (c'est elle qui fait foi : même si le tag est republié, Docker prendra
# exactement la même image).
FROM nextcloud:34.0.5-apache@sha256:b89746a4febc93de82141921417f43b0d5ce69c3746feb485372559e9fbeec3f

# L'appli est déposée dans un dossier À CÔTÉ de Nextcloud. Le hook before-starting la copie
# dans custom_apps au démarrage (on ne « monte » jamais un dossier du PC dans custom_apps :
# ça casse l'installation initiale de Nextcloud).
COPY app /opt/apps-familles/suivi_familles
COPY demo/famille-demo.php /opt/apps-familles/famille-demo.php

# Les « hooks » : scripts que l'image Nextcloud officielle lance à des moments précis.
#   post-installation : une seule fois, juste après la toute première installation ;
#   before-starting   : à chaque démarrage du conteneur.
COPY docker/hooks/post-installation/ /docker-entrypoint-hooks.d/post-installation/
COPY docker/hooks/before-starting/ /docker-entrypoint-hooks.d/before-starting/
RUN chmod +x /docker-entrypoint-hooks.d/*/*.sh \
    && chown -R www-data:www-data /opt/apps-familles
