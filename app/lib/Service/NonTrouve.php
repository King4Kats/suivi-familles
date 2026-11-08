<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Service;

/** Erreur métier traduite par ApiController en HTTP 404 — n'existe pas OU pas d'accès (volontairement indiscernables) */
class NonTrouve extends \RuntimeException {
}
