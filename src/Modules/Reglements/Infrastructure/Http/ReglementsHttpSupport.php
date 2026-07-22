<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http;

use App\Modules\Reglements\Domain\Entity\ReglementInstrument;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentNotFoundException;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Http\JsonRequestSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Helpers HTTP Règlements — résolution instrument, decode+validate, JSON.
 */
final class ReglementsHttpSupport
{
    public static function requireInstrumentByPublicId(
        ReglementInstrumentRepositoryInterface $instrumentRepository,
        string $publicId,
    ): ReglementInstrument {
        $instrument = $instrumentRepository->findByPublicId(
            PublicId::fromString($publicId),
        );
        if ($instrument === null) {
            throw ReglementInstrumentNotFoundException::forPublicId($publicId);
        }

        return $instrument;
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
