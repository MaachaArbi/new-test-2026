<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Controller;

use App\Modules\Party\Application\ListPartyAccounts\ListPartyAccountsHandler;
use App\Modules\Party\Application\ListPartyAccounts\ListPartyAccountsQuery;
use App\Modules\Party\Domain\ValueObject\PartyAccountNature;
use App\Shared\Infrastructure\Http\ListPaginationRequestSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/v1/party-accounts — liste paginée (DBAL / ADR-003).
 */
final class ListPartyAccountsController
{
    public function __construct(
        private readonly ListPartyAccountsHandler $listPartyAccountsHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/party-accounts',
        name: 'api_v1_party_accounts_list',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $violations = [];
        $pagination = ListPaginationRequestSupport::parsePageAndLimit($request, $violations);

        $natureRaw = $request->query->get('nature');
        $searchRaw = $request->query->get('search');

        $nature = null;
        if ($natureRaw !== null && $natureRaw !== '') {
            $allowedNatures = array_map(
                static fn (PartyAccountNature $case): string => $case->value,
                PartyAccountNature::cases(),
            );
            if (!in_array($natureRaw, $allowedNatures, true)) {
                $violations[] = [
                    'field' => 'nature',
                    'message' => 'nature must be person or organization.',
                ];
            } else {
                $nature = $natureRaw;
            }
        }

        $search = ($searchRaw !== null && $searchRaw !== '') ? $searchRaw : null;

        if ($violations !== []) {
            return $this->validationFailedJsonResponseFactory->create($violations);
        }

        $result = ($this->listPartyAccountsHandler)(new ListPartyAccountsQuery(
            page: $pagination['page'],
            limit: $pagination['limit'],
            nature: $nature,
            search: $search,
        ));

        $response = new JsonResponse($result->toArray(), Response::HTTP_OK);
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }
}
