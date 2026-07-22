<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/bookings/{publicId}/transport-segments.
 */
final class AddBookingTransportSegmentRequest
{
    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public mixed $departureAt = null;

    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public mixed $arrivalAt = null;

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $sequenceNumber = 1;

    #[Assert\Type('string')]
    public mixed $carrierCode = null;

    #[Assert\Type('string')]
    public mixed $departureLocation = null;

    #[Assert\Type('string')]
    public mixed $arrivalLocation = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->departureAt = $data['departureAt'] ?? null;
        $request->arrivalAt = $data['arrivalAt'] ?? null;
        $request->sequenceNumber = array_key_exists('sequenceNumber', $data)
            ? $data['sequenceNumber']
            : 1;
        $request->carrierCode = $data['carrierCode'] ?? null;
        $request->departureLocation = $data['departureLocation'] ?? null;
        $request->arrivalLocation = $data['arrivalLocation'] ?? null;

        return $request;
    }
}
