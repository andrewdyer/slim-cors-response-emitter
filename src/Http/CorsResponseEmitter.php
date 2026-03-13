<?php

declare(strict_types=1);

namespace YourVendor\YourPackage\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\ResponseEmitter;

/**
 * Emits HTTP responses with CORS and cache-control headers applied.
 *
 * Centralizes response hardening for cross-origin browser clients while
 * preserving Slim's default response emission behavior.
 */
class CorsResponseEmitter extends ResponseEmitter
{
    /**
     * {@inheritDoc}
     *
     * Applies CORS/cache headers, clears any active output buffer, and emits the response.
     *
     * @param ResponseInterface $response The response to emit.
     */
    public function emit(ResponseInterface $response): void
    {
        $response = $this->applyHeaders($response);

        if (ob_get_contents()) {
            ob_clean();
        }

        parent::emit($response);
    }

    /**
     * Returns a new response instance with default CORS and no-cache headers.
     *
     * The `Access-Control-Allow-Origin` value is derived from `$_SERVER['HTTP_ORIGIN']`
     * when present, otherwise an empty origin is used.
     *
     * @param ResponseInterface $response The response to decorate with headers.
     *
     * @return ResponseInterface The decorated response instance.
     */
    protected function applyHeaders(ResponseInterface $response): ResponseInterface
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        return $response
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization'
            )
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');
    }
}
