<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Security;

/**
 * Port Domain pour le hash / verify de mots de passe.
 *
 * Aucune dépendance framework : l'implémentation (Symfony PasswordHasher)
 * appartient à Infrastructure.
 */
interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;

    public function verify(string $plainPassword, string $hash): bool;
}
