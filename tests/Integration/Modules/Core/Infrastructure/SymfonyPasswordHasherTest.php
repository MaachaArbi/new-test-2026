<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Core\Infrastructure;

use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * SymfonyPasswordHasher via le container — pas de PostgreSQL requis.
 */
final class SymfonyPasswordHasherTest extends KernelTestCase
{
    private PasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var PasswordHasherInterface $passwordHasher */
        $passwordHasher = self::getContainer()->get(PasswordHasherInterface::class);
        $this->passwordHasher = $passwordHasher;
    }

    public function test_hash_differs_from_plain_and_verify_round_trip(): void
    {
        $plain = 'Correct-Horse-Battery-Staple-42';

        $hash = $this->passwordHasher->hash($plain);

        self::assertNotSame($plain, $hash);
        self::assertTrue($this->passwordHasher->verify($plain, $hash));
        self::assertFalse($this->passwordHasher->verify('wrong-password', $hash));
    }

    public function test_successive_hashes_of_same_password_differ_due_to_salting(): void
    {
        $plain = 'same-password-twice';

        $first = $this->passwordHasher->hash($plain);
        $second = $this->passwordHasher->hash($plain);

        self::assertNotSame($first, $second);
        self::assertTrue($this->passwordHasher->verify($plain, $first));
        self::assertTrue($this->passwordHasher->verify($plain, $second));
    }
}
