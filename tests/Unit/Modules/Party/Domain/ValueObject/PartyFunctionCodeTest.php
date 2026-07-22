<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\ValueObject;

use App\Modules\Party\Domain\Exception\InvalidPartyFunctionCodeException;
use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyFunctionCodeTest extends TestCase
{
    #[Test]
    public function it_accepts_a_valid_code(): void
    {
        $code = PartyFunctionCode::fromString('member');

        self::assertSame('member', $code->toString());
    }

    #[Test]
    public function it_trims_whitespace(): void
    {
        $code = PartyFunctionCode::fromString('  gerant  ');

        self::assertSame('gerant', $code->toString());
    }

    #[Test]
    public function it_rejects_empty_value(): void
    {
        try {
            PartyFunctionCode::fromString('');
            self::fail('Expected InvalidPartyFunctionCodeException');
        } catch (InvalidPartyFunctionCodeException $exception) {
            self::assertSame('party_function_code.invalid', $exception->errorCode());
            self::assertSame(['reason' => 'empty'], $exception->context());
        }
    }

    #[Test]
    public function it_rejects_whitespace_only(): void
    {
        try {
            PartyFunctionCode::fromString(' ');
            self::fail('Expected InvalidPartyFunctionCodeException');
        } catch (InvalidPartyFunctionCodeException $exception) {
            // Same path as '' after trim(): empty -> InvalidPartyFunctionCodeException::empty()
            self::assertSame('party_function_code.invalid', $exception->errorCode());
            self::assertSame(['reason' => 'empty'], $exception->context());
        }
    }

    #[Test]
    public function it_rejects_value_longer_than_thirty_chars(): void
    {
        $tooLong = str_repeat('b', 31);

        try {
            PartyFunctionCode::fromString($tooLong);
            self::fail('Expected InvalidPartyFunctionCodeException');
        } catch (InvalidPartyFunctionCodeException $exception) {
            self::assertSame('party_function_code.invalid', $exception->errorCode());
            self::assertSame('too_long', $exception->context()['reason']);
            self::assertSame(30, $exception->context()['max_length']);
        }
    }
}
