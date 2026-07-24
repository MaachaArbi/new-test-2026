<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Invariants d'écriture du grand livre (amount <> 0, origine obligatoire).
 */
final class InvalidSettlementLedgerEntryException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_ledger_entry.invalid';
    }

    public static function amountMustBeNonZero(int $amountMinor): self
    {
        return new self(
            'Ledger entry amount_minor must be non-zero.',
            ['amount_minor' => $amountMinor],
        );
    }

    public static function originRequired(): self
    {
        return new self(
            'Ledger entry requires at least one origin (booking, instrument, invoice, credit note, transfer, or reverses).',
            [],
        );
    }
}
