<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObject;

/**
 * Rôle bénéficiaire d'un settlement (CHECK SQL fixe).
 *
 * Enum PHP volontaire — ensemble fermé en schéma, pas OpenReferentialCode.
 */
enum BeneficiaryRole: string
{
    case Supplier = 'supplier';
    case MainAgency = 'main_agency';
    case Distributor = 'distributor';
}
