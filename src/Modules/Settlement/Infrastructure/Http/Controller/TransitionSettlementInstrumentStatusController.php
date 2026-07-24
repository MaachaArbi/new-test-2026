<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Controller;

use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusCommand;
use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusHandler;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Infrastructure\Http\Dto\SettlementInstrumentResponse;
use App\Modules\Settlement\Infrastructure\Http\Dto\TransitionSettlementInstrumentStatusRequest;
use App\Modules\Settlement\Infrastructure\Http\SettlementHttpSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PATCH /api/v1/settlements/instruments/{publicId}/status.
 */
final class TransitionSettlementInstrumentStatusController
{
    public function __construct(
        private readonly SettlementInstrumentRepositoryInterface $instrumentRepository,
        private readonly ValidatorInterface $validator,
        private readonly TransitionSettlementInstrumentStatusHandler $transitionHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/settlements/instruments/{publicId}/status',
        name: 'api_v1_settlements_instruments_status_transition',
        methods: ['PATCH'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId, Request $request): JsonResponse
    {
        $instrument = SettlementHttpSupport::requireInstrumentByPublicId(
            $this->instrumentRepository,
            $publicId,
        );

        $dto = SettlementHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            TransitionSettlementInstrumentStatusRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $updated = ($this->transitionHandler)(new TransitionSettlementInstrumentStatusCommand(
            instrumentId: (int) $instrument->id(),
            statusCode: is_string($dto->status) ? $dto->status : '',
            reason: is_string($dto->reason) ? $dto->reason : null,
        ));

        return SettlementHttpSupport::json(
            SettlementInstrumentResponse::fromDomain($updated),
            Response::HTTP_OK,
            $this->correlationIdHolder,
        );
    }
}
