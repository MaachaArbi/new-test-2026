<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Plafond côté débit — décision : IMPLÉMENTÉ (voir journal credit-matching).
 * Inféré depuis « partiel autorisé des deux côtés » (schéma), symétrique crédit.
 */
final class SettlementMatchingExceedsDebitException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_matching.exceeds_debit';
    }

    public static function forDebit(
        int $debitEntryId,
        int $debitCapacity,
        int $alreadyMatched,
        int $requested,
    ): self {
        return new self(
            'Matching would exceed remaining debit entry capacity.',
            [
                'debit_entry_id' => $debitEntryId,
                'debit_capacity' => $debitCapacity,
                'already_matched' => $alreadyMatched,
                'requested' => $requested,
            ],
        );
    }
}
