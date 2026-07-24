<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\ValueObject;

/**
 * Cycle de vie d'un instrument de règlement (CHECK SQL fixe).
 *
 * Enum PHP volontaire — pas OpenReferentialCode : ensemble fermé en schéma.
 */
enum SettlementInstrumentStatus: string
{
    case Active = 'active';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}
