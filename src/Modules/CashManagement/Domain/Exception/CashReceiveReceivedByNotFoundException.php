<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashReceiveReceivedByNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_receive.received_by_not_found';
    }

    public static function forId(int $accountId): self
    {
        return new self(
            sprintf('Received-by party account %d was not found.', $accountId),
            ['account_id' => $accountId],
        );
    }
}
