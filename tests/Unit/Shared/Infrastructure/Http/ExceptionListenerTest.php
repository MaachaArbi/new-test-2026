<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ExceptionListener;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExceptionListenerTest extends TestCase
{
    public function test_non_domain_exception_is_logged_fully_and_response_stays_generic(): void
    {
        $secret = 'SECRET_LEAK /var/www/html/boom.php:99 token=abc';
        $original = new RuntimeException($secret);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Unhandled exception',
                self::callback(static function (array $context) use ($original): bool {
                    return ($context['exception'] ?? null) === $original
                        && $context['exception']->getMessage() === $original->getMessage();
                }),
            );

        $translator = $this->createStub(TranslatorInterface::class);

        $correlationIdHolder = new CorrelationIdHolder();
        $correlationIdHolder->set('req-test-500');

        $listener = new ExceptionListener($translator, $correlationIdHolder, $logger);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            Request::create('/api/v1/party-accounts/00000000-0000-4000-8000-000000000001'),
            HttpKernelInterface::MAIN_REQUEST,
            $original,
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('req-test-500', $response->headers->get(RequestIdSubscriber::HEADER_NAME));

        $raw = (string) $response->getContent();
        self::assertSame(
            [
                'error' => [
                    'code' => 'internal_error',
                    'message' => 'An unexpected error occurred.',
                ],
            ],
            json_decode($raw, true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('SECRET_LEAK', $raw);
        self::assertStringNotContainsString('/var/www', $raw);
    }
}
