<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Dto;

use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;

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
final readonly class PostSettlementCreditResponse
{
    /**
     * @return CreditPayload
     */
    public static function fromDomain(SettlementLedgerEntry $entry): array
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
