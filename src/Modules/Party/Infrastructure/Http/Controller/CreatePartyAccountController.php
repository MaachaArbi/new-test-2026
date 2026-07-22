<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Controller;

use App\Modules\Party\Application\CreatePartyAccount\CreatePartyAccountCommand;
use App\Modules\Party\Application\CreatePartyAccount\CreatePartyAccountHandler;
use App\Modules\Party\Infrastructure\Http\Dto\CreatePartyAccountRequest;
use App\Modules\Party\Infrastructure\Http\Dto\PartyAccountResponse;
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
 * POST /api/v1/party-accounts — création person | organization.
 *
 * Validation d'input gérée ici (422) — pas via ExceptionListener (pas DomainException).
 */
final class CreatePartyAccountController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly CreatePartyAccountHandler $createPartyAccountHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/party-accounts',
        name: 'api_v1_party_accounts_create',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $decoded = PartyAccountHttpSupport::decodeJsonObject(
            $request,
            $this->validationFailedJsonResponseFactory,
        );
        if ($decoded instanceof JsonResponse) {
            return $decoded;
        }

        $dto = CreatePartyAccountRequest::fromArray($decoded);
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return $this->validationFailedJsonResponseFactory->create(
                PartyAccountHttpSupport::mapViolations($violations),
            );
        }

        /** @var string $nature */
        $nature = $dto->nature;
        /** @var string $displayName */
        $displayName = $dto->displayName;
        /** @var string|null $email */
        $email = is_string($dto->email) ? $dto->email : null;
        /** @var int|null $parentAccountId */
        $parentAccountId = is_int($dto->parentAccountId) ? $dto->parentAccountId : null;

        $account = ($this->createPartyAccountHandler)(new CreatePartyAccountCommand(
            nature: $nature,
            displayName: $displayName,
            email: $email,
            parentAccountId: $parentAccountId,
        ));

        $publicId = $account->publicId()->toString();
        $response = new JsonResponse(
            PartyAccountResponse::fromDomain($account)->toArray(),
            Response::HTTP_CREATED,
        );
        $response->headers->set('Location', '/api/v1/party-accounts/'.$publicId);
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }
}
