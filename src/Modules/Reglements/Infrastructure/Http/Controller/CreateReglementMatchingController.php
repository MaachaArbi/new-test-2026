<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Controller;

use App\Modules\Reglements\Application\CreateReglementMatching\CreateReglementMatchingCommand;
use App\Modules\Reglements\Application\CreateReglementMatching\CreateReglementMatchingHandler;
use App\Modules\Reglements\Domain\Exception\ReglementLedgerEntryNotFoundException;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Infrastructure\Http\Dto\CreateReglementMatchingRequest;
use App\Modules\Reglements\Infrastructure\Http\Dto\ReglementMatchingResponse;
use App\Modules\Reglements\Infrastructure\Http\ReglementsHttpSupport;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/reglements/matchings.
 */
final class CreateReglementMatchingController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly ReglementLedgerEntryRepositoryInterface $ledgerRepository,
        private readonly CreateReglementMatchingHandler $createMatchingHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/reglements/matchings',
        name: 'api_v1_reglements_matchings_create',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $dto = ReglementsHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            CreateReglementMatchingRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $debitPublicId = is_string($dto->debitEntryPublicId) ? $dto->debitEntryPublicId : '';
        $creditPublicId = is_string($dto->creditEntryPublicId) ? $dto->creditEntryPublicId : '';

        $debit = $this->ledgerRepository->findByPublicId(PublicId::fromString($debitPublicId));
        if ($debit === null || $debit->id() === null) {
            throw ReglementLedgerEntryNotFoundException::forPublicId($debitPublicId);
        }

        $credit = $this->ledgerRepository->findByPublicId(PublicId::fromString($creditPublicId));
        if ($credit === null || $credit->id() === null) {
            throw ReglementLedgerEntryNotFoundException::forPublicId($creditPublicId);
        }

        $matching = ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
            debitEntryId: (int) $debit->id(),
            creditEntryId: (int) $credit->id(),
            matchedAmountMinor: is_int($dto->matchedAmountMinor) ? $dto->matchedAmountMinor : 0,
            isAutomatic: is_bool($dto->isAutomatic) ? $dto->isAutomatic : false,
            matchGroup: is_string($dto->matchGroup) ? $dto->matchGroup : null,
            matchedBy: is_int($dto->matchedBy) ? $dto->matchedBy : null,
        ));

        return ReglementsHttpSupport::json(
            ReglementMatchingResponse::fromDomain($matching, $debit, $credit),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
