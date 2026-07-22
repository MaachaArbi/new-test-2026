<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Security;

use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Adapter Domain ← Symfony PasswordHasher (algorithm: auto via security.yaml).
 */
final class SymfonyPasswordHasher implements PasswordHasherInterface
{
    private \Symfony\Component\PasswordHasher\PasswordHasherInterface $hasher;

    public function __construct(PasswordHasherFactoryInterface $passwordHasherFactory)
    {
        $this->hasher = $passwordHasherFactory->getPasswordHasher(self::class);
    }

    public function hash(string $plainPassword): string
    {
        return $this->hasher->hash($plainPassword);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return $this->hasher->verify($hash, $plainPassword);
    }
}
