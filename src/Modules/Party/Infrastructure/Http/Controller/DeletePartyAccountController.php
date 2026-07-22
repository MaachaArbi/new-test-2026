<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Http\Controller;

use App\Modules\Party\Application\DeletePartyAccount\DeletePartyAccountCommand;
use App\Modules\Party\Application\DeletePartyAccount\DeletePartyAccountHandler;
use App\Modules\Party\Application\DeletePartyAccount\DeletePartyAccountOutcome;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DELETE /api/v1/party-accounts/{publicId} — soft-delete idempotent.
 *
 * Premier soft-delete → 204 ; déjà soft-deleted → 200 ; inexistant → 404.
 */
final class DeletePartyAccountController
{
    public function __construct(
        private readonly DeletePartyAccountHandler $deletePartyAccountHandler,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/party-accounts/{publicId}',
        name: 'api_v1_party_accounts_delete',
        methods: ['DELETE'],
        requirements: ['publicId' => '[0-9a-fA-F-]{36}'],
    )]
    public function __invoke(string $publicId): Response
    {
        $outcome = ($this->deletePartyAccountHandler)(new DeletePartyAccountCommand($publicId));

        $status = match ($outcome) {
            DeletePartyAccountOutcome::SoftDeleted => Response::HTTP_NO_CONTENT,
            DeletePartyAccountOutcome::AlreadyDeleted => Response::HTTP_OK,
        };

        $response = new Response(null, $status);
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }
}
