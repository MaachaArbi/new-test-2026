<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Entity\PartyAccountOrganizationIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyAccountOrganizationIdentityTest extends TestCase
{
    #[Test]
    public function create_stores_all_fields_including_nullables(): void
    {
        $identity = PartyAccountOrganizationIdentity::create(
            accountId: 9,
            taxId: '14455455AM000',
            tradeRegister: null,
            legalFormCode: null,
            isVatSubject: false,
            website: 'https://www.mygo.co',
        );

        self::assertSame(9, $identity->accountId());
        self::assertSame('14455455AM000', $identity->taxId());
        self::assertNull($identity->tradeRegister());
        self::assertNull($identity->legalFormCode());
        self::assertFalse($identity->isVatSubject());
        self::assertSame('https://www.mygo.co', $identity->website());
    }

    #[Test]
    public function create_accepts_all_null_optional_fields(): void
    {
        $identity = PartyAccountOrganizationIdentity::create(
            42,
            null,
            null,
            null,
            true,
            null,
        );

        self::assertSame(42, $identity->accountId());
        self::assertNull($identity->taxId());
        self::assertTrue($identity->isVatSubject());
        self::assertNull($identity->website());
    }
}
