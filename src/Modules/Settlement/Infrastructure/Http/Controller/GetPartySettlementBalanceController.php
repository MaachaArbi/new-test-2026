<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Controller;

use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementInstrumentPartyRoleException;
use App\Modules\Settlement\Domain\Repository\SettlementBalanceRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Settlement\Infrastructure\Http\SettlementHttpSupport;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/v1/party-accounts/{publicId}/settlements/balance.
 */
final class GetPartySettlementBalanceController
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly SettlementBalanceRepositoryInterface $balanceRepository,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/party-accounts/{publicId}/settlements/balance',
        name: 'api_v1_party_accounts_settlements_balance',
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
                throw InvalidSettlementInstrumentPartyRoleException::forValue($partyRole);
            }

            $balance = $this->balanceRepository->findBalance(
                (int) $account->id(),
                $partyRole,
                $currencyCode,
            );

            return SettlementHttpSupport::json(
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

        return SettlementHttpSupport::json(
            [
                'partyAccountPublicId' => $publicId,
                'balances' => $balances,
            ],
            Response::HTTP_OK,
            $this->correlationIdHolder,
        );
    }
}
