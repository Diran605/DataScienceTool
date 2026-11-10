<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A failed call to the Python processing service.
 *
 * Retryable failures are transient (the service is unreachable, restarting or
 * overloaded). Everything else, such as a bad file, an invalid cleaning step or a
 * bug in the service, will fail the same way every time, so retrying only delays
 * the error and fills the run history with duplicate failed steps.
 */
class PythonServiceException extends RuntimeException
{
    /** Gateway/availability errors worth retrying later. */
    private const RETRYABLE_STATUSES = [502, 503, 504];

    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function unreachable(string $uri, Throwable $previous): self
    {
        return new self("Python service is unreachable ({$uri}): {$previous->getMessage()}", retryable: true, previous: $previous);
    }

    public static function fromResponse(string $uri, Response $response): self
    {
        $status = $response->status();
        $detail = $response->json('detail');

        if (! is_string($detail)) {
            $detail = mb_strimwidth($response->body(), 0, 500, '…');
        }

        return new self(
            "Python service request to {$uri} failed ({$status}): {$detail}",
            status: $status,
            retryable: in_array($status, self::RETRYABLE_STATUSES, true),
        );
    }

    public static function invalidResponse(string $uri, string $reason): self
    {
        return new self("Python service returned an invalid response from {$uri}: {$reason}");
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
