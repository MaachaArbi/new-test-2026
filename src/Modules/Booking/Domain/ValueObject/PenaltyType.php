<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObject;

/**
 * Type de pénalité d'annulation (CHECK SQL fixe free/percentage/fixed_amount).
 *
 * Enum PHP volontaire — pas OpenReferentialCode : ensemble fermé en schéma.
 */
enum PenaltyType: string
{
    case Free = 'free';
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
