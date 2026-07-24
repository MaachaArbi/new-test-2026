<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidSettlementTransferPartyRoleException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_transfer.invalid_party_role';
    }

    public static function forValue(string $partyRole): self
    {
        return new self(
            sprintf('Invalid party role "%s" for transfer.', $partyRole),
            ['party_role' => $partyRole],
        );
    }
}
