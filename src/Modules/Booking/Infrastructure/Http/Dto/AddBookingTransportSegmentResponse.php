<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingTransportSegment;
use DateTimeInterface;

/**
 * Réponse POST transport-segments — métier seul, sans id interne.
 */
final readonly class AddBookingTransportSegmentResponse
{
    /**
     * @return array{
     *     sequenceNumber: int,
     *     carrierCode: string|null,
     *     departureAt: string,
     *     arrivalAt: string,
     *     departureLocation: string|null,
     *     arrivalLocation: string|null
     * }
     */
    public static function fromDomain(BookingTransportSegment $segment): array
    {
        return [
            'sequenceNumber' => $segment->sequenceNumber(),
            'carrierCode' => $segment->carrierCode(),
            'departureAt' => $segment->departureAt()->format(DateTimeInterface::ATOM),
            'arrivalAt' => $segment->arrivalAt()->format(DateTimeInterface::ATOM),
            'departureLocation' => $segment->departureLocation(),
            'arrivalLocation' => $segment->arrivalLocation(),
        ];
    }
}
