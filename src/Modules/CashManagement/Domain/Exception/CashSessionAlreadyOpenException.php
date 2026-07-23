<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashSessionAlreadyOpenException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_session.already_open';
    }

    public static function forHolder(int $holderAccountId): self
    {
        return new self(
            sprintf('Holder account %d already has an open cash session.', $holderAccountId),
            ['holder_account_id' => $holderAccountId],
        );
    }
}
