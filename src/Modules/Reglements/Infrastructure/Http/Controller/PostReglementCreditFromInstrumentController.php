<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Controller;

use App\Modules\Reglements\Application\PostReglementCreditFromInstrument\PostReglementCreditFromInstrumentCommand;
use App\Modules\Reglements\Application\PostReglementCreditFromInstrument\PostReglementCreditFromInstrumentHandler;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Modules\Reglements\Infrastructure\Http\Dto\PostReglementCreditResponse;
use App\Modules\Reglements\Infrastructure\Http\ReglementsHttpSupport;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/v1/reglements/instruments/{publicId}/credit — pas de body.
 */
final class PostReglementCreditFromInstrumentController
{
    public function __construct(
        private readonly ReglementInstrumentRepositoryInterface $instrumentRepository,
        private readonly PostReglementCreditFromInstrumentHandler $postCreditHandler,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/reglements/instruments/{publicId}/credit',
        name: 'api_v1_reglements_instruments_credit',
        methods: ['POST'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId): JsonResponse
    {
        $instrument = ReglementsHttpSupport::requireInstrumentByPublicId(
            $this->instrumentRepository,
            $publicId,
        );

        $entry = ($this->postCreditHandler)(new PostReglementCreditFromInstrumentCommand(
            instrumentId: (int) $instrument->id(),
        ));

        return ReglementsHttpSupport::json(
            PostReglementCreditResponse::fromDomain($entry),
            Response::HTTP_CREATED,
            $this->correlationIdHolder,
        );
    }
}
