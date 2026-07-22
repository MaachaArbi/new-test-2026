<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Infrastructure\Http\Dto\BookingResponse;
use App\Modules\Booking\Infrastructure\Http\Dto\CreateBookingRequest;
use App\Shared\Infrastructure\Http\JsonRequestSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings — création d'un booking pivot.
 *
 * Validation d'input gérée ici (422) — pas via ExceptionListener (pas DomainException).
 */
final class CreateBookingController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly CreateBookingHandler $createBookingHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings',
        name: 'api_v1_bookings_create',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $decoded = JsonRequestSupport::decodeJsonObject(
            $request,
            $this->validationFailedJsonResponseFactory,
        );
        if ($decoded instanceof JsonResponse) {
            return $decoded;
        }

        $dto = CreateBookingRequest::fromArray($decoded);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return $this->validationFailedJsonResponseFactory->create(
                JsonRequestSupport::mapViolations($violations),
            );
        }

        /** @var int $folderId */
        $folderId = $dto->folderId;
        /** @var string $serviceTypeCode */
        $serviceTypeCode = $dto->serviceTypeCode;
        /** @var string $statusCode */
        $statusCode = $dto->statusCode;
        /** @var int $customerAccountId */
        $customerAccountId = $dto->customerAccountId;
        /** @var int|null $supplierAccountId */
        $supplierAccountId = is_int($dto->supplierAccountId) ? $dto->supplierAccountId : null;
        /** @var int $officeAccountId */
        $officeAccountId = $dto->officeAccountId;
        /** @var string $startDate */
        $startDate = $dto->startDate;
        /** @var string|null $endDate */
        $endDate = is_string($dto->endDate) ? $dto->endDate : null;
        /** @var string $channelCode */
        $channelCode = $dto->channelCode;
        /** @var string $achatCurrencyCode */
        $achatCurrencyCode = $dto->achatCurrencyCode;
        /** @var string $venteCurrencyCode */
        $venteCurrencyCode = $dto->venteCurrencyCode;
        /** @var string $achatExchangeRate */
        $achatExchangeRate = $dto->achatExchangeRate;
        /** @var string $venteExchangeRate */
        $venteExchangeRate = $dto->venteExchangeRate;
        /** @var int $totalAchatAmount */
        $totalAchatAmount = $dto->totalAchatAmount;
        /** @var int $totalVenteAmount */
        $totalVenteAmount = $dto->totalVenteAmount;
        /** @var int $margeAgenceAmount */
        $margeAgenceAmount = $dto->margeAgenceAmount;
        /** @var int $margeDistributeurAmount */
        $margeDistributeurAmount = $dto->margeDistributeurAmount;
        /** @var int $paidAmount */
        $paidAmount = $dto->paidAmount;
        $paymentStatus = is_string($dto->paymentStatus) && $dto->paymentStatus !== ''
            ? $dto->paymentStatus
            : 'unpaid';

        $booking = ($this->createBookingHandler)(new CreateBookingCommand(
            folderId: $folderId,
            serviceTypeCode: $serviceTypeCode,
            statusCode: $statusCode,
            customerAccountId: $customerAccountId,
            supplierAccountId: $supplierAccountId,
            officeAccountId: $officeAccountId,
            startDate: $startDate,
            endDate: $endDate,
            achatCurrencyCode: $achatCurrencyCode,
            venteCurrencyCode: $venteCurrencyCode,
            achatExchangeRate: $achatExchangeRate,
            venteExchangeRate: $venteExchangeRate,
            totalAchatAmount: $totalAchatAmount,
            totalVenteAmount: $totalVenteAmount,
            margeAgenceAmount: $margeAgenceAmount,
            margeDistributeurAmount: $margeDistributeurAmount,
            paidAmount: $paidAmount,
            channelCode: $channelCode,
            paymentStatus: $paymentStatus,
        ));

        $publicId = $booking->publicId()->toString();
        $response = new JsonResponse(
            BookingResponse::fromDomain($booking)->toArray(),
            Response::HTTP_CREATED,
        );
        $response->headers->set('Location', '/api/v1/bookings/'.$publicId);
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }
}
