<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réponses JSON d'erreur de validation d'input (422) — hors DomainException.
 */
final class ValidationFailedJsonResponseFactory
{
    public function __construct(
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    /**
     * @param list<array{field: string, message: string}> $violations
     */
    public function create(array $violations): JsonResponse
    {
        $response = new JsonResponse(
            [
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'Validation failed.',
                    'violations' => $violations,
                ],
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }
}
