<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Initialise (ou réutilise X-Request-Id) l'ID de corrélation pour la requête HTTP.
 */
final class RequestIdSubscriber implements EventSubscriberInterface
{
    public const HEADER_NAME = 'X-Request-Id';

    public function __construct(
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
            KernelEvents::RESPONSE => ['onKernelResponse', -255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $incoming = $event->getRequest()->headers->get(self::HEADER_NAME);
        $requestId = ($incoming !== null && $incoming !== '')
            ? $incoming
            : Uuid::uuid4()->toString();

        $this->correlationIdHolder->set($requestId);
        $event->getRequest()->attributes->set('request_id', $requestId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set(
            self::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );
    }
}
