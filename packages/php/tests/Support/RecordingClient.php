<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A tiny PSR-18 client that records requests and replays queued responses.
 */
final class RecordingClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|callable(RequestInterface): ResponseInterface> */
    private array $queue;

    /**
     * @param list<ResponseInterface|callable(RequestInterface): ResponseInterface> $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function json(int $status, array $body, array $headers = []): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'] + $headers,
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('RecordingClient: no more queued responses');
        }
        return is_callable($next) ? $next($request) : $next;
    }

    public function lastRequest(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }
}
