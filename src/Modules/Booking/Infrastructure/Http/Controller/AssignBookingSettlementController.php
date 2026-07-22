<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\AssignBookingSettlement\AssignBookingSettlementCommand;
use App\Modules\Booking\Application\AssignBookingSettlement\AssignBookingSettlementHandler;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\AssignBookingSettlementRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\AssignBookingSettlementResponse;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/settlements.
 *
 * Pas de Location — aucun GET individuel settlement pour l'instant.
 */
final class AssignBookingSettlementController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly AssignBookingSettlementHandler $assignBookingSettlementHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/settlements',
        name: 'api_v1_bookings_settlements_assign',
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
            AssignBookingSettlementRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var int $beneficiaryAccountId */
        $beneficiaryAccountId = $dto->beneficiaryAccountId;
        /** @var string $beneficiaryRoleRaw */
        $beneficiaryRoleRaw = $dto->beneficiaryRole;
        /** @var int $amountOwedMinor */
        $amountOwedMinor = $dto->amountOwedMinor;
        /** @var string $currencyCode */
        $currencyCode = $dto->currencyCode;

        $settlement = ($this->assignBookingSettlementHandler)(new AssignBookingSettlementCommand(
            bookingId: (int) $booking->id(),
            beneficiaryAccountId: $beneficiaryAccountId,
            beneficiaryRole: BeneficiaryRole::from($beneficiaryRoleRaw),
            amountOwedMinor: $amountOwedMinor,
            currencyCode: $currencyCode,
            amountSettledDirectMinor: is_int($dto->amountSettledDirectMinor)
                ? $dto->amountSettledDirectMinor
                : 0,
            rate: is_string($dto->rate) ? $dto->rate : null,
            resalePriceAmountMinor: is_int($dto->resalePriceAmountMinor)
                ? $dto->resalePriceAmountMinor
                : null,
            createdBy: is_int($dto->createdBy) ? $dto->createdBy : null,
        ));

        return BookingHttpSupport::json(
            AssignBookingSettlementResponse::fromDomain($settlement),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
