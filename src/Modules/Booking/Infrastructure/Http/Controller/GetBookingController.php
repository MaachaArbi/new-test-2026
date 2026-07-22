<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Infrastructure\Http\Dto\BookingResponse;
use App\Shared\Domain\ValueObject\PublicId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/v1/bookings/{publicId} — lecture d'un booking par public_id.
 *
 * Note ADR-003 : findByPublicId() via Repository Domain acceptable pour ce
 * cas simple 1-ligne ; les futurs endpoints de LISTE devront passer par DBAL
 * direct, pas par réhydratation Domain.
 */
final class GetBookingController
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings/{publicId}',
        name: 'api_v1_bookings_get',
        methods: ['GET'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId): JsonResponse
    {
        $booking = $this->bookingRepository->findByPublicId(
            PublicId::fromString($publicId),
        );

        if ($booking === null) {
            throw BookingNotFoundException::forPublicId($publicId);
        }

        return new JsonResponse(
            BookingResponse::fromDomain($booking)->toArray(),
            Response::HTTP_OK,
        );
    }
}
