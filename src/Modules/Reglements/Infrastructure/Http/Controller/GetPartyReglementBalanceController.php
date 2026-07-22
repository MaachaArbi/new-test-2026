<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Controller;

use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentPartyRoleException;
use App\Modules\Reglements\Domain\Repository\ReglementBalanceRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Reglements\Infrastructure\Http\ReglementsHttpSupport;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/v1/party-accounts/{publicId}/reglements/balance.
 */
final class GetPartyReglementBalanceController
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly ReglementBalanceRepositoryInterface $balanceRepository,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/party-accounts/{publicId}/reglements/balance',
        name: 'api_v1_party_accounts_reglements_balance',
        methods: ['GET'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId, Request $request): JsonResponse
    {
        $account = $this->partyAccountRepository->findByPublicId(
            PublicId::fromString($publicId),
        );
        if ($account === null) {
            throw PartyAccountNotFoundException::forPublicId($publicId);
        }

        $partyRole = $request->query->get('partyRole');
        $currencyCode = $request->query->get('currencyCode');

        if (is_string($partyRole) && $partyRole !== '' && is_string($currencyCode) && $currencyCode !== '') {
            try {
                InstrumentPartyRole::from($partyRole);
            } catch (\ValueError) {
                throw InvalidReglementInstrumentPartyRoleException::forValue($partyRole);
            }

            $balance = $this->balanceRepository->findBalance(
                (int) $account->id(),
                $partyRole,
                $currencyCode,
            );

            return ReglementsHttpSupport::json(
                [
                    'partyAccountPublicId' => $publicId,
                    'balances' => $balance === null ? [] : [[
                        'partyRole' => $partyRole,
                        'currencyCode' => strtoupper($currencyCode),
                        'balanceMinor' => $balance['balanceMinor'],
                        'entryCount' => $balance['entryCount'],
                        'updatedAt' => $balance['updatedAt']->format(DATE_ATOM),
                    ]],
                ],
                Response::HTTP_OK,
                $this->correlationIdHolder,
            );
        }

        $books = $this->balanceRepository->findAllBalancesForParty((int) $account->id());
        $balances = [];
        foreach ($books as $book) {
            $balances[] = [
                'partyRole' => $book['partyRole'],
                'currencyCode' => $book['currencyCode'],
                'balanceMinor' => $book['balanceMinor'],
                'entryCount' => $book['entryCount'],
                'updatedAt' => $book['updatedAt']->format(DATE_ATOM),
            ];
        }

        return ReglementsHttpSupport::json(
            [
                'partyAccountPublicId' => $publicId,
                'balances' => $balances,
            ],
            Response::HTTP_OK,
            $this->correlationIdHolder,
        );
    }
}
