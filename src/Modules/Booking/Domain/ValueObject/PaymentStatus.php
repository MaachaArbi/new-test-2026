<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObject;

/**
 * Statut de paiement booking (CHECK SQL fixe unpaid/partial/paid).
 *
 * Enum PHP volontaire — pas OpenReferentialCode : ensemble fermé en schéma,
 * pas une table référentielle ouverte.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
}
