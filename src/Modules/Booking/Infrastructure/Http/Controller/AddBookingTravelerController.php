<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\CreateBookingTraveler\CreateBookingTravelerCommand;
use App\Modules\Booking\Application\CreateBookingTraveler\CreateBookingTravelerHandler;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingTravelerRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingTravelerResponse;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Http\JsonRequestSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/travelers — ajouter un voyageur snapshot.
 *
 * Pas de header Location : aucun GET individuel voyageur pour l'instant.
 */
final class AddBookingTravelerController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly CreateBookingTravelerHandler $createBookingTravelerHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/travelers',
        name: 'api_v1_bookings_travelers_add',
        methods: ['POST'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId, Request $request): JsonResponse
    {
        $booking = $this->bookingRepository->findByPublicId(
            PublicId::fromString($publicId),
        );
        if ($booking === null) {
            throw BookingNotFoundException::forPublicId($publicId);
        }

        $decoded = JsonRequestSupport::decodeJsonObject(
            $request,
            $this->validationFailedJsonResponseFactory,
        );
        if ($decoded instanceof JsonResponse) {
            return $decoded;
        }

        $dto = AddBookingTravelerRequest::fromArray($decoded);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return $this->validationFailedJsonResponseFactory->create(
                JsonRequestSupport::mapViolations($violations),
            );
        }

        /** @var string $firstName */
        $firstName = $dto->firstName;
        /** @var string $lastName */
        $lastName = $dto->lastName;

        $birthDate = null;
        if (is_string($dto->birthDate) && $dto->birthDate !== '') {
            $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dto->birthDate);
            if ($birthDate === false) {
                $birthDate = null;
            }
        }

        $traveler = ($this->createBookingTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: (int) $booking->id(),
            firstName: $firstName,
            lastName: $lastName,
            hotelRoomId: is_int($dto->hotelRoomId) ? $dto->hotelRoomId : null,
            partyAccountId: is_int($dto->partyAccountId) ? $dto->partyAccountId : null,
            civility: is_string($dto->civility) ? $dto->civility : null,
            phone: is_string($dto->phone) ? $dto->phone : null,
            email: is_string($dto->email) ? $dto->email : null,
            age: is_int($dto->age) ? $dto->age : null,
            birthDate: $birthDate,
            birthPlace: is_string($dto->birthPlace) ? $dto->birthPlace : null,
            nationalityCountryId: is_int($dto->nationalityCountryId) ? $dto->nationalityCountryId : null,
            residenceCountryId: is_int($dto->residenceCountryId) ? $dto->residenceCountryId : null,
            documentType: is_string($dto->documentType) ? $dto->documentType : null,
            documentNumber: is_string($dto->documentNumber) ? $dto->documentNumber : null,
            drivingLicenseNumber: is_string($dto->drivingLicenseNumber) ? $dto->drivingLicenseNumber : null,
            isPaxLeader: $dto->isPaxLeader === true,
            ticketNumber: is_string($dto->ticketNumber) ? $dto->ticketNumber : null,
            pnr: is_string($dto->pnr) ? $dto->pnr : null,
            travelClass: is_string($dto->travelClass) ? $dto->travelClass : null,
        ));

        $response = new JsonResponse(
            AddBookingTravelerResponse::fromDomain($traveler),
            Response::HTTP_CREATED,
        );
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }
}
