<?php

namespace App\Services;

use RuntimeException;

/** A bounded, retryable local-inference queue outcome from Flask. */
final class MlServiceBusyException extends RuntimeException
{
    public function __construct(
        private readonly string $queueCode,
        private readonly int $httpStatus,
        private readonly int $retryAfter,
    ) {
        parent::__construct('The local AI inference queue is busy.', $httpStatus);
    }

    public function queueCode(): string
    {
        return $this->queueCode;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfter;
    }
}