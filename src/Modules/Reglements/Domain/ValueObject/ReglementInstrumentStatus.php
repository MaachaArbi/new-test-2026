<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\ValueObject;

/**
 * Cycle de vie d'un instrument de règlement (CHECK SQL fixe).
 *
 * Enum PHP volontaire — pas OpenReferentialCode : ensemble fermé en schéma.
 */
enum ReglementInstrumentStatus: string
{
    case Active = 'active';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}
