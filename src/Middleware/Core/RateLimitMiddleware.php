<?php

namespace Express\Middleware\Core;

use Express\Middleware\Core\BaseMiddleware;
use Express\Http\Request;

/**
 * Middleware de Rate Limiting para Express PHP.
 */
class RateLimitMiddleware extends BaseMiddleware
{
    /** @var array<string, mixed> */
    private array $options;

    /** @param array<string, mixed> $options */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(
            [
            'windowMs' => 900000, // 15 minutes
            'max' => 100, // limit each IP to 100 requests per windowMs
            'message' => 'Too many requests, please try again later.',
            'statusCode' => 429,
            'keyGenerator' => null,
            'skipSuccessfulRequests' => false,
            'skipFailedRequests' => false
            ],
            $options
        );
    }

    public function handle($request, $response, callable $next)
    {
        $key = $this->getKey($request);
        $now = time();
        $windowStart = $now - ($this->options['windowMs'] / 1000);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        // Clean old entries
        $_SESSION['rate_limit'] = array_filter(
            $_SESSION['rate_limit'],
            function ($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            }
        );

        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }

        // Clean old entries for this key
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function ($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            }
        );

        $currentCount = count($_SESSION['rate_limit'][$key]);

        if ($currentCount >= $this->options['max']) {
            return $response->status($this->options['statusCode'])->json(
                [
                'error' => true,
                'message' => $this->options['message']
                ]
            );
        }

        // Record this request
        $_SESSION['rate_limit'][$key][] = $now;

        return $next();
    }

    /**
     * @param Request $request
     */
    private function getKey($request): string
    {
        if ($this->options['keyGenerator'] && is_callable($this->options['keyGenerator'])) {
            $result = call_user_func($this->options['keyGenerator'], $request);
            return is_string($result) ? $result : 'unknown';
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return is_string($remoteAddr) ? $remoteAddr : 'unknown';
    }
}
