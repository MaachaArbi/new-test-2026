<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Controller;

use App\Modules\Reglements\Application\UnmatchReglementMatching\UnmatchReglementMatchingCommand;
use App\Modules\Reglements\Application\UnmatchReglementMatching\UnmatchReglementMatchingHandler;
use App\Modules\Reglements\Domain\Exception\ReglementLedgerEntryNotFoundException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingNotFoundException;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementMatchingRepositoryInterface;
use App\Modules\Reglements\Infrastructure\Http\Dto\ReglementMatchingResponse;
use App\Modules\Reglements\Infrastructure\Http\ReglementsHttpSupport;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DELETE /api/v1/reglements/matchings/{publicId} — soft unmatch.
 */
final class UnmatchReglementMatchingController
{
    public function __construct(
        private readonly ReglementMatchingRepositoryInterface $matchingRepository,
        private readonly ReglementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly UnmatchReglementMatchingHandler $unmatchHandler,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/reglements/matchings/{publicId}',
        name: 'api_v1_reglements_matchings_unmatch',
        methods: ['DELETE'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId): JsonResponse
    {
        $existing = $this->matchingRepository->findByPublicId(PublicId::fromString($publicId));
        if ($existing === null || $existing->id() === null) {
            throw ReglementMatchingNotFoundException::forPublicId($publicId);
        }

        $matching = ($this->unmatchHandler)(new UnmatchReglementMatchingCommand(
            (int) $existing->id(),
        ));

        $debit = $this->ledgerRepository->findById($matching->debitEntryId());
        $credit = $this->ledgerRepository->findById($matching->creditEntryId());
        if ($debit === null) {
            throw ReglementLedgerEntryNotFoundException::forId($matching->debitEntryId());
        }
        if ($credit === null) {
            throw ReglementLedgerEntryNotFoundException::forId($matching->creditEntryId());
        }

        return ReglementsHttpSupport::json(
            ReglementMatchingResponse::fromDomain($matching, $debit, $credit),
            Response::HTTP_OK,
            $this->correlationIdHolder,
        );
    }
}
