<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Logging;

use App\Shared\Domain\Exception\InvalidEmailException;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Vérifie que Monolog est câblé (JSON + processors).
 * La vérif bout-en-bout HTTP complète attendra le premier Controller réel.
 */
final class StructuredLoggingIntegrationTest extends KernelTestCase
{
    public function test_logger_writes_json_with_request_id_and_domain_exception_fields(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        /** @var CorrelationIdHolder $holder */
        $holder = self::getContainer()->get(CorrelationIdHolder::class);
        $holder->set('integration-request-id');

        $logFile = $kernel->getLogDir().'/test.log';
        if (is_file($logFile)) {
            unlink($logFile);
        }

        /** @var LoggerInterface $logger */
        $logger = self::getContainer()->get(LoggerInterface::class);
        $exception = InvalidEmailException::invalidFormat('bad@');
        $logger->error($exception->getMessage(), ['exception' => $exception]);

        self::assertFileExists($logFile);
        $raw = file_get_contents($logFile);
        self::assertNotFalse($raw);
        $lines = array_values(array_filter(explode("\n", trim($raw))));
        self::assertNotEmpty($lines);
        $lastLine = $lines[count($lines) - 1];

        /** @var array<string, mixed> $payload */
        $payload = json_decode($lastLine, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload['extra'] ?? null);

        /** @var array<string, mixed> $extra */
        $extra = $payload['extra'];
        self::assertSame('integration-request-id', $extra['request_id'] ?? null);
        self::assertSame('email.invalid_format', $extra['error_code'] ?? null);
        self::assertSame(['value' => 'bad@'], $extra['domain_context'] ?? null);
        self::assertArrayHasKey('datetime', $payload);
        self::assertArrayHasKey('level_name', $payload);
        self::assertArrayHasKey('message', $payload);
    }

    public function test_request_id_subscriber_reuses_incoming_header(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        /** @var CorrelationIdHolder $holder */
        $holder = self::getContainer()->get(CorrelationIdHolder::class);
        $holder->reset();

        /** @var RequestIdSubscriber $subscriber */
        $subscriber = self::getContainer()->get(RequestIdSubscriber::class);

        $request = Request::create('/');
        $request->headers->set(RequestIdSubscriber::HEADER_NAME, 'from-client-abc');

        $subscriber->onKernelRequest(new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        ));

        self::assertSame('from-client-abc', $holder->get());
        self::assertSame('from-client-abc', $request->attributes->get('request_id'));

        $response = new Response();
        $subscriber->onKernelResponse(new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        self::assertSame('from-client-abc', $response->headers->get(RequestIdSubscriber::HEADER_NAME));
    }
}
