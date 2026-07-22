<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\AssignBookingPayerSplit\AssignBookingPayerSplitCommand;
use App\Modules\Booking\Application\AssignBookingPayerSplit\AssignBookingPayerSplitHandler;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\AssignBookingPayerSplitRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\AssignBookingPayerSplitResponse;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/payer-splits.
 *
 * Pas de Location — aucun GET individuel payer-split pour l'instant.
 */
final class AssignBookingPayerSplitController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly AssignBookingPayerSplitHandler $assignBookingPayerSplitHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/payer-splits',
        name: 'api_v1_bookings_payer_splits_assign',
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
            AssignBookingPayerSplitRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var int $payerAccountId */
        $payerAccountId = $dto->payerAccountId;
        /** @var int $amountMinor */
        $amountMinor = $dto->amountMinor;
        /** @var string $currencyCode */
        $currencyCode = $dto->currencyCode;

        $split = ($this->assignBookingPayerSplitHandler)(new AssignBookingPayerSplitCommand(
            bookingId: (int) $booking->id(),
            payerAccountId: $payerAccountId,
            amountMinor: $amountMinor,
            currencyCode: $currencyCode,
            createdBy: is_int($dto->createdBy) ? $dto->createdBy : null,
        ));

        return BookingHttpSupport::json(
            AssignBookingPayerSplitResponse::fromDomain($split),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
