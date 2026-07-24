<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\ValueObject;

use App\Modules\Party\Domain\Exception\InvalidPartyRoleCodeException;
use App\Modules\Party\Domain\ValueObject\PartyRoleCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyRoleCodeTest extends TestCase
{
    #[Test]
    public function it_accepts_a_valid_code(): void
    {
        $code = PartyRoleCode::fromString('customer');

        self::assertSame('customer', $code->toString());
    }

    #[Test]
    public function it_trims_whitespace(): void
    {
        $code = PartyRoleCode::fromString('  supplier  ');

        self::assertSame('supplier', $code->toString());
    }

    #[Test]
    public function it_rejects_empty_value(): void
    {
        try {
            PartyRoleCode::fromString('');
            self::fail('Expected InvalidPartyRoleCodeException');
        } catch (InvalidPartyRoleCodeException $exception) {
            self::assertSame('party_role_code.invalid', $exception->errorCode());
            self::assertSame(['reason' => 'empty'], $exception->context());
        }
    }

    #[Test]
    public function it_rejects_whitespace_only(): void
    {
        try {
            PartyRoleCode::fromString(' ');
            self::fail('Expected InvalidPartyRoleCodeException');
        } catch (InvalidPartyRoleCodeException $exception) {
            // Same path as '' after trim(): empty -> InvalidPartyRoleCodeException::empty()
            self::assertSame('party_role_code.invalid', $exception->errorCode());
            self::assertSame(['reason' => 'empty'], $exception->context());
        }
    }

    #[Test]
    public function it_rejects_value_longer_than_thirty_chars(): void
    {
        $tooLong = str_repeat('a', 31);

        try {
            PartyRoleCode::fromString($tooLong);
            self::fail('Expected InvalidPartyRoleCodeException');
        } catch (InvalidPartyRoleCodeException $exception) {
            self::assertSame('party_role_code.invalid', $exception->errorCode());
            self::assertSame('too_long', $exception->context()['reason']);
            self::assertSame(30, $exception->context()['max_length']);
        }
    }
}
