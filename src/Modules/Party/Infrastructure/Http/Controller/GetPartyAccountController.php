<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Controller;

use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Party\Infrastructure\Http\Dto\PartyAccountResponse;
use App\Shared\Domain\ValueObject\PublicId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/v1/party-accounts/{publicId} — lecture d'un compte par public_id.
 *
 * Note ADR-003 : findByPublicId() via Repository Domain acceptable pour ce
 * cas simple 1-ligne ; les futurs endpoints de LISTE devront passer par DBAL
 * direct, pas par réhydratation Domain.
 */
final class GetPartyAccountController
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
    ) {
    }

    #[Route(
        path: '/api/v1/party-accounts/{publicId}',
        name: 'api_v1_party_accounts_get',
        methods: ['GET'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId): JsonResponse
    {
        $account = $this->partyAccountRepository->findByPublicId(
            PublicId::fromString($publicId),
        );

        if ($account === null) {
            throw PartyAccountNotFoundException::forPublicId($publicId);
        }

        return new JsonResponse(
            PartyAccountResponse::fromDomain($account)->toArray(),
            Response::HTTP_OK,
        );
    }
}
