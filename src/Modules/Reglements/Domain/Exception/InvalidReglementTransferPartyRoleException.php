<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidReglementTransferPartyRoleException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_transfer.invalid_party_role';
    }

    public static function forValue(string $partyRole): self
    {
        return new self(
            sprintf('Invalid party role "%s" for transfer.', $partyRole),
            ['party_role' => $partyRole],
        );
    }
}
