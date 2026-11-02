<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Http\Client\RequestException;

class ReleaseWhenMoneybirdRateLimited
{
    /**
     * Seconds to wait when Moneybird does not send a usable Retry-After header.
     */
    public const int DEFAULT_RETRY_AFTER = 60;

    /**
     * Release the job back onto the queue when Moneybird answers 429, waiting
     * as long as its Retry-After header asks. Any other failure is rethrown so
     * the job's normal retry and failure handling applies.
     *
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 429) {
                throw $exception;
            }

            $retryAfter = (int) $exception->response->header('Retry-After');

            $job->release($retryAfter > 0 ? $retryAfter : self::DEFAULT_RETRY_AFTER);
        }
    }
}
