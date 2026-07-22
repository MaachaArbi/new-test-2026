<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Décodage JSON + mapping violations — partagé Create/Update HTTP.
 */
final class JsonRequestSupport
{
    /**
     * @return array<string, mixed>|JsonResponse
     */
    public static function decodeJsonObject(
        Request $request,
        ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
    ): array|JsonResponse {
        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $validationFailedJsonResponseFactory->create([
                ['field' => '', 'message' => 'Request body must be valid JSON.'],
            ]);
        }

        if (!is_array($decoded)) {
            return $validationFailedJsonResponseFactory->create([
                ['field' => '', 'message' => 'Request body must be a JSON object.'],
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $decoded;

        return $payload;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public static function mapViolations(ConstraintViolationListInterface $violations): array
    {
        $mapped = [];
        foreach ($violations as $violation) {
            $mapped[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $mapped;
    }
}
