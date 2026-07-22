<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Dto;

use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Entity\ReglementMatching;

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
final readonly class ReglementMatchingResponse
{
    /**
     * @return MatchingPayload
     */
    public static function fromDomain(
        ReglementMatching $matching,
        ReglementLedgerEntry $debit,
        ReglementLedgerEntry $credit,
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
