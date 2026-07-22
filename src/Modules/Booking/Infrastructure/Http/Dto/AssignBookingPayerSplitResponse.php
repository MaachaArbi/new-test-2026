<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingPayerSplit;

/**
 * Réponse POST payer-split — métier, sans id interne ni bookingId.
 */
final readonly class AssignBookingPayerSplitResponse
{
    /**
     * @return array{
     *     payerAccountId: int,
     *     amountMinor: int,
     *     currencyCode: string
     * }
     */
    public static function fromDomain(BookingPayerSplit $split): array
    {
        $amount = $split->amount();

        return [
            'payerAccountId' => $split->payerAccountId(),
            'amountMinor' => $amount->amount(),
            'currencyCode' => $split->currencyCode(),
        ];
    }
}
