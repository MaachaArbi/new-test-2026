<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidEmailException;
use App\Shared\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    #[Test]
    public function it_rejects_invalid_format(): void
    {
        try {
            Email::fromString('not-an-email');
            self::fail('Expected InvalidEmailException');
        } catch (InvalidEmailException $exception) {
            self::assertSame('email.invalid_format', $exception->errorCode());
            self::assertSame(['value' => 'not-an-email'], $exception->context());
        }
    }

    #[Test]
    public function it_accepts_valid_format(): void
    {
        $email = Email::fromString('user@example.com');

        self::assertSame('user@example.com', $email->toString());
    }

    #[Test]
    public function emails_with_different_casing_are_equal(): void
    {
        $a = Email::fromString('Case.Test@Example.COM');
        $b = Email::fromString('case.test@example.com');

        self::assertTrue($a->equals($b));
    }
}
