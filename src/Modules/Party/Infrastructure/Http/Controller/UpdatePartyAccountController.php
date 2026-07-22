<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Controller;

use App\Modules\Party\Application\UpdatePartyAccount\UpdatePartyAccountCommand;
use App\Modules\Party\Application\UpdatePartyAccount\UpdatePartyAccountHandler;
use App\Modules\Party\Infrastructure\Http\Dto\PartyAccountResponse;
use App\Modules\Party\Infrastructure\Http\Dto\UpdatePartyAccountRequest;
use App\Modules\Party\Infrastructure\Http\PartyAccountHttpSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PATCH /api/v1/party-accounts/{publicId} — mise à jour partielle.
 */
final class UpdatePartyAccountController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly UpdatePartyAccountHandler $updatePartyAccountHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/party-accounts/{publicId}',
        name: 'api_v1_party_accounts_update',
        methods: ['PATCH'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId, Request $request): JsonResponse
    {
        $decoded = PartyAccountHttpSupport::decodeJsonObject(
            $request,
            $this->validationFailedJsonResponseFactory,
        );
        if ($decoded instanceof JsonResponse) {
            return $decoded;
        }

        $dto = UpdatePartyAccountRequest::fromArray($decoded);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return $this->validationFailedJsonResponseFactory->create(
                PartyAccountHttpSupport::mapViolations($violations),
            );
        }

        $account = ($this->updatePartyAccountHandler)(new UpdatePartyAccountCommand(
            publicId: $publicId,
            hasDisplayName: $dto->hasDisplayName,
            displayName: is_string($dto->displayName) ? $dto->displayName : null,
            hasIsDisabled: $dto->hasIsDisabled,
            isDisabled: is_bool($dto->isDisabled) ? $dto->isDisabled : null,
        ));

        $response = new JsonResponse(
            PartyAccountResponse::fromDomain($account)->toArray(),
            Response::HTTP_OK,
        );
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }
}
