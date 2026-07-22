<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\ValueObject;

/**
 * Mode de traçabilité d'instrument (CHECK SQL fixe — 3 valeurs).
 *
 * Enum PHP volontaire — ensemble fermé en schéma, pas un référentiel ouvert.
 */
enum InstrumentTrackingMode: string
{
    case Individual = 'individual';
    case Aggregate = 'aggregate';
    case NotApplicable = 'not_applicable';
}
