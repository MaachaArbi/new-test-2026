<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Controller;

use App\Modules\Settlement\Application\UnmatchSettlementMatching\UnmatchSettlementMatchingCommand;
use App\Modules\Settlement\Application\UnmatchSettlementMatching\UnmatchSettlementMatchingHandler;
use App\Modules\Settlement\Domain\Exception\SettlementLedgerEntryNotFoundException;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingNotFoundException;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementMatchingRepositoryInterface;
use App\Modules\Settlement\Infrastructure\Http\Dto\SettlementMatchingResponse;
use App\Modules\Settlement\Infrastructure\Http\SettlementHttpSupport;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DELETE /api/v1/settlements/matchings/{publicId} — soft unmatch.
 */
final class UnmatchSettlementMatchingController
{
    public function __construct(
        private readonly SettlementMatchingRepositoryInterface $matchingRepository,
        private readonly SettlementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly UnmatchSettlementMatchingHandler $unmatchHandler,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/settlements/matchings/{publicId}',
        name: 'api_v1_settlements_matchings_unmatch',
        methods: ['DELETE'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId): JsonResponse
    {
        $existing = $this->matchingRepository->findByPublicId(PublicId::fromString($publicId));
        if ($existing === null || $existing->id() === null) {
            throw SettlementMatchingNotFoundException::forPublicId($publicId);
        }

        $matching = ($this->unmatchHandler)(new UnmatchSettlementMatchingCommand(
            (int) $existing->id(),
        ));

        $debit = $this->ledgerRepository->findById($matching->debitEntryId());
        $credit = $this->ledgerRepository->findById($matching->creditEntryId());
        if ($debit === null) {
            throw SettlementLedgerEntryNotFoundException::forId($matching->debitEntryId());
        }
        if ($credit === null) {
            throw SettlementLedgerEntryNotFoundException::forId($matching->creditEntryId());
        }

        return SettlementHttpSupport::json(
            SettlementMatchingResponse::fromDomain($matching, $debit, $credit),
            Response::HTTP_OK,
            $this->correlationIdHolder,
        );
    }
}
