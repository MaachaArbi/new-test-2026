<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentCommand;
use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentHandler;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingTransportSegmentRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingTransportSegmentResponse;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/transport-segments.
 *
 * Pas de Location — aucun GET individuel segment pour l'instant.
 */
final class AddBookingTransportSegmentController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly AddBookingTransportSegmentHandler $addBookingTransportSegmentHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/transport-segments',
        name: 'api_v1_bookings_transport_segments_add',
        methods: ['POST'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId, Request $request): JsonResponse
    {
        $booking = BookingHttpSupport::requireByPublicId($this->bookingRepository, $publicId);

        $dto = BookingHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            AddBookingTransportSegmentRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var string $departureAtRaw */
        $departureAtRaw = $dto->departureAt;
        /** @var string $arrivalAtRaw */
        $arrivalAtRaw = $dto->arrivalAt;

        $segment = ($this->addBookingTransportSegmentHandler)(new AddBookingTransportSegmentCommand(
            bookingId: (int) $booking->id(),
            departureAt: new DateTimeImmutable($departureAtRaw),
            arrivalAt: new DateTimeImmutable($arrivalAtRaw),
            sequenceNumber: is_int($dto->sequenceNumber) ? $dto->sequenceNumber : 1,
            carrierCode: is_string($dto->carrierCode) ? $dto->carrierCode : null,
            departureLocation: is_string($dto->departureLocation) ? $dto->departureLocation : null,
            arrivalLocation: is_string($dto->arrivalLocation) ? $dto->arrivalLocation : null,
        ));

        return BookingHttpSupport::json(
            AddBookingTransportSegmentResponse::fromDomain($segment),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
