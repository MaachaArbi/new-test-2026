<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomCommand;
use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomHandler;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingHotelRoomRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingHotelRoomResponse;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/hotel-rooms.
 *
 * Pas de Location — aucun GET individuel chambre pour l'instant.
 */
final class AddBookingHotelRoomController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly AddBookingHotelRoomHandler $addBookingHotelRoomHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/hotel-rooms',
        name: 'api_v1_bookings_hotel_rooms_add',
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
            AddBookingHotelRoomRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $room = ($this->addBookingHotelRoomHandler)(new AddBookingHotelRoomCommand(
            bookingId: (int) $booking->id(),
            roomType: is_string($dto->roomType) ? $dto->roomType : null,
        ));

        return BookingHttpSupport::json(
            AddBookingHotelRoomResponse::fromDomain($room),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
