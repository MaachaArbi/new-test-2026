<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Exception;

use App\Modules\Party\Domain\Exception\PartyAccountMustBeOrganizationException;
use App\Modules\Party\Domain\Exception\PartyAccountNotFoundException;
use App\Modules\Party\Domain\Exception\PartyAccountOfficeCodeAlreadyUsedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyOrganizationExtensionExceptionsTest extends TestCase
{
    #[Test]
    public function must_be_organization_exposes_code_and_context(): void
    {
        $exception = PartyAccountMustBeOrganizationException::forAccount(15, 'person');

        self::assertSame('party_account.must_be_organization', $exception->errorCode());
        self::assertSame(
            [
                'account_id' => 15,
                'nature' => 'person',
            ],
            $exception->context(),
        );
        self::assertStringContainsString('15', $exception->getMessage());
        self::assertStringContainsString('person', $exception->getMessage());
    }

    #[Test]
    public function office_code_already_used_exposes_code_and_context(): void
    {
        $exception = PartyAccountOfficeCodeAlreadyUsedException::forCode('MYGO-2023');

        self::assertSame('party_account_office.code_already_used', $exception->errorCode());
        self::assertSame(['office_code' => 'MYGO-2023'], $exception->context());
        self::assertStringContainsString('MYGO-2023', $exception->getMessage());
    }

    #[Test]
    public function not_found_exposes_code_and_context(): void
    {
        $exception = PartyAccountNotFoundException::forId(404);

        self::assertSame('party_account.not_found', $exception->errorCode());
        self::assertSame(['account_id' => 404], $exception->context());
        self::assertStringContainsString('404', $exception->getMessage());
    }
}
