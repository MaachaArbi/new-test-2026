<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\AddBookingCancellationTier\AddBookingCancellationTierCommand;
use App\Modules\Booking\Application\AddBookingCancellationTier\AddBookingCancellationTierHandler;
use App\Modules\Booking\Domain\Exception\BookingCancellationPolicyNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingCancellationPolicyRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\PenaltyType;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingCancellationTierRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\AddBookingCancellationTierResponse;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/cancellation-policy/{policyId}/tiers.
 *
 * policyId dans l'URL (pas le body) : appartenance au booking vérifiée ici.
 */
final class AddBookingCancellationTierController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingCancellationPolicyRepositoryInterface $policyRepository,
        private readonly ValidatorInterface $validator,
        private readonly AddBookingCancellationTierHandler $addBookingCancellationTierHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/cancellation-policy/{policyId}/tiers',
        name: 'api_v1_bookings_cancellation_policy_tiers_add',
        methods: ['POST'],
        requirements: [
            'publicId' => '[0-9a-fA-F-]{36}',
            'policyId' => '\d+',
        ],
    )]
    public function __invoke(string $publicId, int $policyId, Request $request): JsonResponse
    {
        $booking = BookingHttpSupport::requireByPublicId($this->bookingRepository, $publicId);

        $policy = $this->policyRepository->findById($policyId);
        if ($policy === null || $policy->bookingId() !== (int) $booking->id()) {
            throw BookingCancellationPolicyNotFoundException::forId($policyId);
        }

        $dto = BookingHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            AddBookingCancellationTierRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var string $penaltyTypeRaw */
        $penaltyTypeRaw = $dto->penaltyType;
        /** @var int $daysBeforeStart */
        $daysBeforeStart = $dto->daysBeforeStart;

        $tier = ($this->addBookingCancellationTierHandler)(new AddBookingCancellationTierCommand(
            policyId: $policyId,
            daysBeforeStart: $daysBeforeStart,
            penaltyType: PenaltyType::from($penaltyTypeRaw),
            penaltyValue: is_string($dto->penaltyValue) ? $dto->penaltyValue : null,
            thresholdTime: is_string($dto->thresholdTime) ? $dto->thresholdTime : null,
            minStayNights: is_int($dto->minStayNights) ? $dto->minStayNights : null,
            maxStayNights: is_int($dto->maxStayNights) ? $dto->maxStayNights : null,
            sortOrder: is_int($dto->sortOrder) ? $dto->sortOrder : 0,
        ));

        return BookingHttpSupport::json(
            AddBookingCancellationTierResponse::fromDomain($tier),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
