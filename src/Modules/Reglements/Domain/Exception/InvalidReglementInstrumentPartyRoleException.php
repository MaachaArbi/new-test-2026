<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidReglementInstrumentPartyRoleException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_instrument.invalid_party_role';
    }

    public static function forValue(string $partyRole): self
    {
        return new self(
            sprintf('Invalid party role "%s" for instrument.', $partyRole),
            ['party_role' => $partyRole],
        );
    }
}
