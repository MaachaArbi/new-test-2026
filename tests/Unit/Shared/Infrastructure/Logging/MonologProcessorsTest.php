<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Domain\Exception\InvalidEmailException;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\DomainExceptionProcessor;
use App\Shared\Infrastructure\Logging\RequestIdProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MonologProcessorsTest extends TestCase
{
    #[Test]
    public function request_id_processor_injects_holder_value(): void
    {
        $holder = new CorrelationIdHolder();
        $holder->set('req-fixed-123');
        $processor = new RequestIdProcessor($holder);

        $record = $processor($this->record('hello'));

        self::assertSame('req-fixed-123', $record->extra['request_id']);
    }

    #[Test]
    public function request_id_processor_generates_id_when_holder_empty(): void
    {
        $processor = new RequestIdProcessor(new CorrelationIdHolder());

        $record = $processor($this->record('hello'));

        self::assertNotSame('', $record->extra['request_id']);
        self::assertIsString($record->extra['request_id']);
    }

    #[Test]
    public function domain_exception_processor_adds_error_code_and_context(): void
    {
        $exception = InvalidEmailException::invalidFormat('bad');
        $processor = new DomainExceptionProcessor();

        $record = $processor($this->record('domain failed', ['exception' => $exception]));

        self::assertSame('email.invalid_format', $record->extra['error_code']);
        self::assertSame(['value' => 'bad'], $record->extra['domain_context']);
    }

    #[Test]
    public function domain_exception_processor_ignores_other_exceptions(): void
    {
        $processor = new DomainExceptionProcessor();

        $record = $processor($this->record('other', ['exception' => new \RuntimeException('x')]));

        self::assertArrayNotHasKey('error_code', $record->extra);
        self::assertArrayNotHasKey('domain_context', $record->extra);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(string $message, array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Error,
            message: $message,
            context: $context,
        );
    }
}
