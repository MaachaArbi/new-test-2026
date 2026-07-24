<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Controller;

use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentCommand;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentHandler;
use App\Modules\Settlement\Infrastructure\Http\Dto\CreateSettlementInstrumentRequest;
use App\Modules\Settlement\Infrastructure\Http\Dto\SettlementInstrumentResponse;
use App\Modules\Settlement\Infrastructure\Http\SettlementHttpSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * POST /api/v1/settlements/instruments.
 */
final class CreateSettlementInstrumentController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly CreateSettlementInstrumentHandler $createHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/settlements/instruments',
        name: 'api_v1_settlements_instruments_create',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $dto = SettlementHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            CreateSettlementInstrumentRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $instrument = ($this->createHandler)(new CreateSettlementInstrumentCommand(
            partyAccountId: is_int($dto->partyAccountId) ? $dto->partyAccountId : 0,
            partyRole: is_string($dto->partyRole) ? $dto->partyRole : '',
            currencyCode: is_string($dto->currencyCode) ? $dto->currencyCode : '',
            paymentMethodId: is_int($dto->paymentMethodId) ? $dto->paymentMethodId : 0,
            amountMinor: is_int($dto->amountMinor) ? $dto->amountMinor : 0,
            instrumentRef: is_string($dto->instrumentRef) ? $dto->instrumentRef : null,
            bankName: is_string($dto->bankName) ? $dto->bankName : null,
            dueDate: is_string($dto->dueDate) ? $dto->dueDate : null,
            issuedOn: is_string($dto->issuedOn) ? $dto->issuedOn : null,
            metadata: is_array($dto->metadata) ? $dto->metadata : [],
            officeAccountId: is_int($dto->officeAccountId) ? $dto->officeAccountId : null,
        ));

        return SettlementHttpSupport::json(
            SettlementInstrumentResponse::fromDomain($instrument),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
