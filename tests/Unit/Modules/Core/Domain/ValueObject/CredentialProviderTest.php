<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Core\Domain\ValueObject;

use App\Modules\Core\Domain\ValueObject\CredentialProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CredentialProviderTest extends TestCase
{
    #[Test]
    public function backed_values_match_schema_v1_1(): void
    {
        self::assertSame('local', CredentialProvider::Local->value);
        self::assertSame('google', CredentialProvider::Google->value);
        self::assertSame('facebook', CredentialProvider::Facebook->value);
        self::assertSame('api_key', CredentialProvider::ApiKey->value);
        self::assertSame('sso_interne', CredentialProvider::SsoInterne->value);
    }
}
