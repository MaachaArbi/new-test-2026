<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http;

use App\Shared\Infrastructure\Http\JsonRequestSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Facade Party — délègue à JsonRequestSupport (évite clones phpcpd).
 */
final class PartyAccountHttpSupport
{
    /**
     * @return array<string, mixed>|JsonResponse
     */
    public static function decodeJsonObject(
        Request $request,
        ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
    ): array|JsonResponse {
        return JsonRequestSupport::decodeJsonObject($request, $validationFailedJsonResponseFactory);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public static function mapViolations(ConstraintViolationListInterface $violations): array
    {
        return JsonRequestSupport::mapViolations($violations);
    }
}
