<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\SuiviFamilles\Service;

/** Erreur métier traduite par ApiController en HTTP 403 — on voit la famille mais on n'a pas le droit de faire ça */
class Interdit extends \RuntimeException {
}
