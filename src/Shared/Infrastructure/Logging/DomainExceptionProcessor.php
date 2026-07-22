<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Domain\Exception\DomainException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Enrichit les logs contenant une DomainException avec errorCode() + context().
 */
final class DomainExceptionProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;
        if (!$exception instanceof DomainException) {
            return $record;
        }

        $extra = $record->extra;
        $extra['error_code'] = $exception->errorCode();
        $extra['domain_context'] = $exception->context();

        return $record->with(extra: $extra);
    }
}
