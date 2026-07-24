<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Controller;

use App\Modules\Settlement\Application\CreateSettlementMatching\CreateSettlementMatchingCommand;
use App\Modules\Settlement\Application\CreateSettlementMatching\CreateSettlementMatchingHandler;
use App\Modules\Settlement\Domain\Exception\SettlementLedgerEntryNotFoundException;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Infrastructure\Http\Dto\CreateSettlementMatchingRequest;
use App\Modules\Settlement\Infrastructure\Http\Dto\SettlementMatchingResponse;
use App\Modules\Settlement\Infrastructure\Http\SettlementHttpSupport;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/settlements/matchings.
 */
final class CreateSettlementMatchingController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SettlementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly CreateSettlementMatchingHandler $createMatchingHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/settlements/matchings',
        name: 'api_v1_settlements_matchings_create',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $dto = SettlementHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            CreateSettlementMatchingRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $debitPublicId = is_string($dto->debitEntryPublicId) ? $dto->debitEntryPublicId : '';
        $creditPublicId = is_string($dto->creditEntryPublicId) ? $dto->creditEntryPublicId : '';

        $debit = $this->ledgerRepository->findByPublicId(PublicId::fromString($debitPublicId));
        if ($debit === null || $debit->id() === null) {
            throw SettlementLedgerEntryNotFoundException::forPublicId($debitPublicId);
        }

        $credit = $this->ledgerRepository->findByPublicId(PublicId::fromString($creditPublicId));
        if ($credit === null || $credit->id() === null) {
            throw SettlementLedgerEntryNotFoundException::forPublicId($creditPublicId);
        }

        $matching = ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
            debitEntryId: (int) $debit->id(),
            creditEntryId: (int) $credit->id(),
            matchedAmountMinor: is_int($dto->matchedAmountMinor) ? $dto->matchedAmountMinor : 0,
            isAutomatic: is_bool($dto->isAutomatic) ? $dto->isAutomatic : false,
            matchGroup: is_string($dto->matchGroup) ? $dto->matchGroup : null,
            matchedBy: is_int($dto->matchedBy) ? $dto->matchedBy : null,
        ));

        return SettlementHttpSupport::json(
            SettlementMatchingResponse::fromDomain($matching, $debit, $credit),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
