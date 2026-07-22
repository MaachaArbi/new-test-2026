<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\SetBookingCarRentalDetail\SetBookingCarRentalDetailCommand;
use App\Modules\Booking\Application\SetBookingCarRentalDetail\SetBookingCarRentalDetailHandler;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\SetBookingCarRentalDetailRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\SetBookingCarRentalDetailResponse;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PUT /api/v1/bookings/{publicId}/car-rental-detail — set 1-1 (create ou replace).
 *
 * 200 (pas 201) : sémantique idempotente d'écrasement.
 * Pas de Location — aucun GET individuel pour l'instant.
 */
final class SetBookingCarRentalDetailController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly SetBookingCarRentalDetailHandler $setBookingCarRentalDetailHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/car-rental-detail',
        name: 'api_v1_bookings_car_rental_detail_set',
        methods: ['PUT'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId, Request $request): JsonResponse
    {
        $booking = BookingHttpSupport::requireByPublicId($this->bookingRepository, $publicId);

        $dto = BookingHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            SetBookingCarRentalDetailRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $pickupAt = null;
        if (is_string($dto->pickupAt) && $dto->pickupAt !== '') {
            $pickupAt = new DateTimeImmutable($dto->pickupAt);
        }
        $dropoffAt = null;
        if (is_string($dto->dropoffAt) && $dto->dropoffAt !== '') {
            $dropoffAt = new DateTimeImmutable($dto->dropoffAt);
        }

        $detail = ($this->setBookingCarRentalDetailHandler)(new SetBookingCarRentalDetailCommand(
            bookingId: (int) $booking->id(),
            vehicleCategory: is_string($dto->vehicleCategory) ? $dto->vehicleCategory : null,
            vehicleBrandModel: is_string($dto->vehicleBrandModel) ? $dto->vehicleBrandModel : null,
            pickupAt: $pickupAt,
            dropoffAt: $dropoffAt,
            pickupLocation: is_string($dto->pickupLocation) ? $dto->pickupLocation : null,
            dropoffLocation: is_string($dto->dropoffLocation) ? $dto->dropoffLocation : null,
        ));

        return BookingHttpSupport::json(
            SetBookingCarRentalDetailResponse::fromDomain($detail),
            Response::HTTP_OK,
            $this->correlationIdHolder,
        );
    }
}
