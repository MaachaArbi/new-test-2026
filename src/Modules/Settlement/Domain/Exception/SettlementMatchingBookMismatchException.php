<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class SettlementMatchingBookMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_matching.book_mismatch';
    }

    public static function forEntries(int $debitEntryId, int $creditEntryId): self
    {
        return new self(
            'Debit and credit ledger entries must belong to the same book (account, role, currency).',
            ['debit_entry_id' => $debitEntryId, 'credit_entry_id' => $creditEntryId],
        );
    }
}
