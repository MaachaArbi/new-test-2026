<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Controller;

use App\Modules\Settlement\Application\PostSettlementCreditFromInstrument\PostSettlementCreditFromInstrumentCommand;
use App\Modules\Settlement\Application\PostSettlementCreditFromInstrument\PostSettlementCreditFromInstrumentHandler;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Infrastructure\Http\Dto\PostSettlementCreditResponse;
use App\Modules\Settlement\Infrastructure\Http\SettlementHttpSupport;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/v1/settlements/instruments/{publicId}/credit — pas de body.
 */
final class PostSettlementCreditFromInstrumentController
{
    public function __construct(
        private readonly SettlementInstrumentRepositoryInterface $instrumentRepository,
        private readonly PostSettlementCreditFromInstrumentHandler $postCreditHandler,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/settlements/instruments/{publicId}/credit',
        name: 'api_v1_settlements_instruments_credit',
        methods: ['POST'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId): JsonResponse
    {
        $instrument = SettlementHttpSupport::requireInstrumentByPublicId(
            $this->instrumentRepository,
            $publicId,
        );

        $entry = ($this->postCreditHandler)(new PostSettlementCreditFromInstrumentCommand(
            instrumentId: (int) $instrument->id(),
        ));

        return SettlementHttpSupport::json(
            PostSettlementCreditResponse::fromDomain($entry),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
