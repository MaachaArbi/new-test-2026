<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashRoutingTypeNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_routing_type.not_found';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Cash routing type "%s" was not found.', $code),
            ['code' => $code],
        );
    }
}
