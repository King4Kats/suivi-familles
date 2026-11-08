<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Service;

/** Erreur métier traduite par ApiController en HTTP 400 — saisie incorrecte (message montré tel quel à l'utilisateur) */
class Invalide extends \InvalidArgumentException {
}
