<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Enrichit le payload JWT avec le public_id uniquement (ADR-018).
 * Pas d'account_id interne : un JWT est signé, pas chiffré.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
final class EnrichJwtPayloadListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof SecurityUser) {
            return;
        }

        $payload = $event->getData();
        $payload['public_id'] = $user->publicId();
        $event->setData($payload);
    }
}
