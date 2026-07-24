<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class SettlementTransferPostingFailedException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_transfer.posting_failed';
    }

    public static function emptyResult(): self
    {
        return new self(
            'settlement_post_transfer() returned no transfer id.',
            ['result' => null],
        );
    }

    public static function nonNumericResult(mixed $raw): self
    {
        return new self(
            'settlement_post_transfer() returned a non-numeric transfer id.',
            ['result' => is_scalar($raw) ? $raw : get_debug_type($raw)],
        );
    }
}
