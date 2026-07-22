<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Injecte request_id dans chaque enregistrement Monolog de la requête courante.
 */
final class RequestIdProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;
        $extra['request_id'] = $this->correlationIdHolder->get();

        return $record->with(extra: $extra);
    }
}
