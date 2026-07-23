<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\ValueObject;

/**
 * Statut de session de caisse (CHECK SQL fixe — 3 valeurs).
 *
 * Enum PHP volontaire — ensemble fermé en schéma, pas un référentiel ouvert.
 */
enum CashSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Validated = 'validated';
}
