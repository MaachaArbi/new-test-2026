<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Dto;

use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\Entity\SettlementMatching;

/**
 * Réponse matching — publicId uniquement (pas d'id interne d'écriture).
 *
 * @phpstan-type MatchingPayload array{
 *     publicId: string,
 *     debitEntryPublicId: string,
 *     creditEntryPublicId: string,
 *     matchedAmountMinor: int,
 *     isAutomatic: bool,
 *     matchGroup: string|null,
 *     matchedAt: string,
 *     isActive: bool,
 *     unmatchedAt: string|null
 * }
 */
final readonly class SettlementMatchingResponse
{
    /**
     * @return MatchingPayload
     */
    public static function fromDomain(
        SettlementMatching $matching,
        SettlementLedgerEntry $debit,
        SettlementLedgerEntry $credit,
    ): array {
        return [
            'publicId' => $matching->publicId()->toString(),
            'debitEntryPublicId' => $debit->publicId()->toString(),
            'creditEntryPublicId' => $credit->publicId()->toString(),
            'matchedAmountMinor' => $matching->matchedAmountMinor(),
            'isAutomatic' => $matching->isAutomatic(),
            'matchGroup' => $matching->matchGroup(),
            'matchedAt' => $matching->matchedAt()->format(DATE_ATOM),
            'isActive' => $matching->isActive(),
            'unmatchedAt' => $matching->unmatchedAt()?->format(DATE_ATOM),
        ];
    }
}
