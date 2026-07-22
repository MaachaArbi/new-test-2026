<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingTransportSegment;

use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Domain\Entity\BookingTransportSegment;
use App\Modules\Booking\Domain\Repository\BookingTransportSegmentRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : ajouter un tronçon transport (extension transport_segment).
 */
final class AddBookingTransportSegmentHandler
{
    private const REQUIRED_EXTENSION = 'transport_segment';

    public function __construct(
        private readonly AssertBookingServiceType $assertBookingServiceType,
        private readonly BookingTransportSegmentRepositoryInterface $segmentRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AddBookingTransportSegmentCommand $command): BookingTransportSegment
    {
        ($this->assertBookingServiceType)($command->bookingId, self::REQUIRED_EXTENSION);

        $segment = BookingTransportSegment::create(
            $command->bookingId,
            $command->departureAt,
            $command->arrivalAt,
            $command->sequenceNumber,
            $command->carrierCode,
            $command->departureLocation,
            $command->arrivalLocation,
        );

        $this->segmentRepository->save($segment);
        $this->unitOfWork->commit();

        return $segment;
    }
}
