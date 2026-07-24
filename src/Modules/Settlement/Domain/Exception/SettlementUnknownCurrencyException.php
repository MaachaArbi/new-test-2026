<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class SettlementUnknownCurrencyException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement.unknown_currency';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Unknown currency code "%s".', $code),
            ['code' => $code],
        );
    }
}
