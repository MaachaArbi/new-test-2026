<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Core\Domain\Entity;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Exception\InvalidCoreCredentialStateException;
use App\Modules\Core\Domain\ValueObject\CredentialProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CoreCredentialTest extends TestCase
{
    #[Test]
    public function create_local_ok(): void
    {
        $credential = CoreCredential::createLocal(
            accountId: 10,
            passwordHash: '$argon2id$v=19$m=65536,t=4,p=1$opaque',
            isPrimary: true,
        );

        self::assertNull($credential->id());
        self::assertSame(10, $credential->accountId());
        self::assertSame(CredentialProvider::Local, $credential->provider());
        self::assertNull($credential->providerUserId());
        self::assertSame('$argon2id$v=19$m=65536,t=4,p=1$opaque', $credential->passwordHash());
        self::assertTrue($credential->isPrimary());
        self::assertTrue($credential->isEnabled());
        self::assertNull($credential->lastLoginAt());
    }

    #[Test]
    public function create_oauth_ok(): void
    {
        $credential = CoreCredential::createOAuth(
            accountId: 20,
            provider: CredentialProvider::Google,
            providerUserId: 'google-sub-abc',
            isPrimary: false,
        );

        self::assertNull($credential->id());
        self::assertSame(20, $credential->accountId());
        self::assertSame(CredentialProvider::Google, $credential->provider());
        self::assertSame('google-sub-abc', $credential->providerUserId());
        self::assertNull($credential->passwordHash());
        self::assertFalse($credential->isPrimary());
        self::assertTrue($credential->isEnabled());
        self::assertNull($credential->lastLoginAt());
    }

    #[Test]
    public function create_oauth_with_local_provider_is_rejected(): void
    {
        try {
            CoreCredential::createOAuth(
                accountId: 30,
                provider: CredentialProvider::Local,
                providerUserId: 'should-not-matter',
                isPrimary: true,
            );
            self::fail('Expected InvalidCoreCredentialStateException');
        } catch (InvalidCoreCredentialStateException $exception) {
            self::assertSame('core_credential.oauth_provider_cannot_be_local', $exception->errorCode());
            self::assertSame(
                [
                    'account_id' => 30,
                    'provider' => 'local',
                ],
                $exception->context(),
            );
        }
    }

    #[Test]
    public function disable_and_enable_change_state(): void
    {
        $credential = CoreCredential::createLocal(1, 'hash', false);
        self::assertTrue($credential->isEnabled());

        $credential->disable();
        self::assertFalse($credential->isEnabled());

        $credential->enable();
        self::assertTrue($credential->isEnabled());
    }

    #[Test]
    public function mark_as_primary_sets_flag(): void
    {
        $credential = CoreCredential::createOAuth(
            1,
            CredentialProvider::Facebook,
            'fb-1',
            isPrimary: false,
        );
        self::assertFalse($credential->isPrimary());

        $credential->markAsPrimary();

        self::assertTrue($credential->isPrimary());
    }

    #[Test]
    public function record_login_updates_last_login_at(): void
    {
        $credential = CoreCredential::createLocal(1, 'hash', true);
        self::assertNull($credential->lastLoginAt());

        $before = new DateTimeImmutable();
        $credential->recordLogin();
        $after = new DateTimeImmutable();

        $lastLoginAt = $credential->lastLoginAt();
        self::assertInstanceOf(DateTimeImmutable::class, $lastLoginAt);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $lastLoginAt->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $lastLoginAt->getTimestamp());
    }
}
