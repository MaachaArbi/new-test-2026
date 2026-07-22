<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\ValueObject;

use App\Modules\Party\Domain\Exception\InvalidPartyAccountGroupTypeCodeException;
use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyAccountGroupTypeCodeTest extends TestCase
{
    #[Test]
    public function it_accepts_a_valid_code(): void
    {
        $code = PartyAccountGroupTypeCode::fromString('commercial');

        self::assertSame('commercial', $code->toString());
    }

    #[Test]
    public function it_trims_whitespace(): void
    {
        $code = PartyAccountGroupTypeCode::fromString('  zone  ');

        self::assertSame('zone', $code->toString());
    }

    #[Test]
    public function it_rejects_empty_value(): void
    {
        try {
            PartyAccountGroupTypeCode::fromString('');
            self::fail('Expected InvalidPartyAccountGroupTypeCodeException');
        } catch (InvalidPartyAccountGroupTypeCodeException $exception) {
            self::assertSame('party_account_group_type_code.invalid', $exception->errorCode());
            self::assertSame(['reason' => 'empty'], $exception->context());
        }
    }

    #[Test]
    public function it_rejects_whitespace_only(): void
    {
        try {
            PartyAccountGroupTypeCode::fromString(' ');
            self::fail('Expected InvalidPartyAccountGroupTypeCodeException');
        } catch (InvalidPartyAccountGroupTypeCodeException $exception) {
            self::assertSame('party_account_group_type_code.invalid', $exception->errorCode());
            self::assertSame(['reason' => 'empty'], $exception->context());
        }
    }

    #[Test]
    public function it_rejects_value_longer_than_thirty_chars(): void
    {
        $tooLong = str_repeat('c', 31);

        try {
            PartyAccountGroupTypeCode::fromString($tooLong);
            self::fail('Expected InvalidPartyAccountGroupTypeCodeException');
        } catch (InvalidPartyAccountGroupTypeCodeException $exception) {
            self::assertSame('party_account_group_type_code.invalid', $exception->errorCode());
            self::assertSame('too_long', $exception->context()['reason']);
            self::assertSame(30, $exception->context()['max_length']);
        }
    }
}
