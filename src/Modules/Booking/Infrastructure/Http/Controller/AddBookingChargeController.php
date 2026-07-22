<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\AddBookingCharge\AddBookingChargeCommand;
use App\Modules\Booking\Application\AddBookingCharge\AddBookingChargeHandler;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingChargeRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingChargeResponse;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/charges.
 *
 * Pas de Location — aucun GET individuel charge pour l'instant.
 */
final class AddBookingChargeController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly AddBookingChargeHandler $addBookingChargeHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/charges',
        name: 'api_v1_bookings_charges_add',
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
            AddBookingChargeRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var string $chargeTypeCode */
        $chargeTypeCode = $dto->chargeTypeCode;
        /** @var int $achatAmountMinor */
        $achatAmountMinor = $dto->achatAmountMinor;
        /** @var string $achatCurrencyCode */
        $achatCurrencyCode = $dto->achatCurrencyCode;
        /** @var int $venteAmountMinor */
        $venteAmountMinor = $dto->venteAmountMinor;
        /** @var string $venteCurrencyCode */
        $venteCurrencyCode = $dto->venteCurrencyCode;
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($dto->metadata) ? $dto->metadata : [];

        $charge = ($this->addBookingChargeHandler)(new AddBookingChargeCommand(
            bookingId: (int) $booking->id(),
            chargeTypeCode: $chargeTypeCode,
            achatAmount: Money::fromMinorUnits($achatAmountMinor, $achatCurrencyCode),
            venteAmount: Money::fromMinorUnits($venteAmountMinor, $venteCurrencyCode),
            travelerId: is_int($dto->travelerId) ? $dto->travelerId : null,
            segmentId: is_int($dto->segmentId) ? $dto->segmentId : null,
            label: is_string($dto->label) ? $dto->label : null,
            metadata: $metadata,
            sortOrder: is_int($dto->sortOrder) ? $dto->sortOrder : 0,
        ));

        return BookingHttpSupport::json(
            AddBookingChargeResponse::fromDomain($charge),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
