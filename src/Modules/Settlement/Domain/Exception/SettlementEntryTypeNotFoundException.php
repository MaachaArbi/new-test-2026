<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class SettlementEntryTypeNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_entry_type.not_found';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Settlement entry type "%s" was not found.', $code),
            ['code' => $code],
        );
    }
}
