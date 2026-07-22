<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Controller;

use App\Modules\Reglements\Application\TransitionReglementInstrumentStatus\TransitionReglementInstrumentStatusCommand;
use App\Modules\Reglements\Application\TransitionReglementInstrumentStatus\TransitionReglementInstrumentStatusHandler;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Modules\Reglements\Infrastructure\Http\Dto\ReglementInstrumentResponse;
use App\Modules\Reglements\Infrastructure\Http\Dto\TransitionReglementInstrumentStatusRequest;
use App\Modules\Reglements\Infrastructure\Http\ReglementsHttpSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PATCH /api/v1/reglements/instruments/{publicId}/status.
 */
final class TransitionReglementInstrumentStatusController
{
    public function __construct(
        private readonly ReglementInstrumentRepositoryInterface $instrumentRepository,
        private readonly ValidatorInterface $validator,
        private readonly TransitionReglementInstrumentStatusHandler $transitionHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/reglements/instruments/{publicId}/status',
        name: 'api_v1_reglements_instruments_status_transition',
        methods: ['PATCH'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId, Request $request): JsonResponse
    {
        $instrument = ReglementsHttpSupport::requireInstrumentByPublicId(
            $this->instrumentRepository,
            $publicId,
        );

        $dto = ReglementsHttpSupport::decodeAndValidate(
            $request,
            $this->validator,
            $this->validationFailedJsonResponseFactory,
            TransitionReglementInstrumentStatusRequest::fromArray(...),
        );
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $updated = ($this->transitionHandler)(new TransitionReglementInstrumentStatusCommand(
            instrumentId: (int) $instrument->id(),
            statusCode: is_string($dto->status) ? $dto->status : '',
            reason: is_string($dto->reason) ? $dto->reason : null,
        ));

        return ReglementsHttpSupport::json(
            ReglementInstrumentResponse::fromDomain($updated),
            Response::HTTP_OK,
            $this->correlationIdHolder,
        );
    }
}
