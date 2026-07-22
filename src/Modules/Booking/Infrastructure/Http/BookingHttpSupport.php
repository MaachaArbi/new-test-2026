<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Http\JsonRequestSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Helpers HTTP Booking — résolution parent, decode+validate, JSON (anti-phpcpd).
 */
final class BookingHttpSupport
{
    public static function requireByPublicId(
        BookingRepositoryInterface $bookingRepository,
        string $publicId,
    ): Booking {
        $booking = $bookingRepository->findByPublicId(
            PublicId::fromString($publicId),
        );
        if ($booking === null) {
            throw BookingNotFoundException::forPublicId($publicId);
        }

        return $booking;
    }

    /**
     * @template T of object
     *
     * @param callable(array<string, mixed>): T $fromArray
     *
     * @return T|JsonResponse
     */
    public static function decodeAndValidate(
        Request $request,
        ValidatorInterface $validator,
        ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        callable $fromArray,
    ): mixed {
        $decoded = JsonRequestSupport::decodeJsonObject(
            $request,
            $validationFailedJsonResponseFactory,
        );
        if ($decoded instanceof JsonResponse) {
            return $decoded;
        }

        $dto = $fromArray($decoded);
        $violations = $validator->validate($dto);
        if ($violations->count() > 0) {
            return $validationFailedJsonResponseFactory->create(
                JsonRequestSupport::mapViolations($violations),
            );
        }

        return $dto;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(
        array $data,
        int $status,
        CorrelationIdHolder $correlationIdHolder,
    ): JsonResponse {
        $response = new JsonResponse($data, $status);
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $correlationIdHolder->get(),
        );

        return $response;
    }
}
