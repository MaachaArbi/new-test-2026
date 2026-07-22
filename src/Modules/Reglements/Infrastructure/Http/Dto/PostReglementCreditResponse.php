<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Dto;

use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;

/**
 * Réponse crédit grand livre — publicId de l'écriture, sans id interne.
 *
 * @phpstan-type CreditPayload array{
 *     publicId: string,
 *     partyRole: string,
 *     currencyCode: string,
 *     amountMinor: int,
 *     effectiveDate: string,
 *     memo: string|null
 * }
 */
final readonly class PostReglementCreditResponse
{
    /**
     * @return CreditPayload
     */
    public static function fromDomain(ReglementLedgerEntry $entry): array
    {
        return [
            'publicId' => $entry->publicId()->toString(),
            'partyRole' => $entry->partyRole()->value,
            'currencyCode' => $entry->currencyCode(),
            'amountMinor' => $entry->amountMinor(),
            'effectiveDate' => $entry->effectiveDate()->format('Y-m-d'),
            'memo' => $entry->memo(),
        ];
    }
}
