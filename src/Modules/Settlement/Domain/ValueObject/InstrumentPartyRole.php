<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\ValueObject;

/**
 * Rôle du tiers porteur d'un instrument (CHECK SQL fixe client/fournisseur).
 *
 * Enum PHP volontaire — ensemble fermé en schéma, pas un référentiel ouvert.
 */
enum InstrumentPartyRole: string
{
    case Client = 'client';
    case Fournisseur = 'fournisseur';
}
