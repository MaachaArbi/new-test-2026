<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\CreateBookingCancellationPolicy\CreateBookingCancellationPolicyCommand;
use App\Modules\Booking\Application\CreateBookingCancellationPolicy\CreateBookingCancellationPolicyHandler;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\BookingHttpSupport;
use App\Modules\Booking\Infrastructure\Http\Dto\CreateBookingCancellationPolicyRequest;
use App\Modules\Booking\Infrastructure\Http\Dto\CreateBookingCancellationPolicyResponse;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/bookings/{publicId}/cancellation-policy.
 *
 * Pas de Location — pas de GET policy individuel ; l'`id` est dans le corps
 * pour enchaîner sur les tiers.
 */
final class CreateBookingCancellationPolicyController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ValidatorInterface $validator,
        private readonly CreateBookingCancellationPolicyHandler $createBookingCancellationPolicyHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}/cancellation-policy',
        name: 'api_v1_bookings_cancellation_policy_create',
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
            CreateBookingCancellationPolicyRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $policy = ($this->createBookingCancellationPolicyHandler)(
            new CreateBookingCancellationPolicyCommand(
                bookingId: (int) $booking->id(),
                roomId: is_int($dto->roomId) ? $dto->roomId : null,
            ),
        );

        return BookingHttpSupport::json(
            CreateBookingCancellationPolicyResponse::fromDomain($policy),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
