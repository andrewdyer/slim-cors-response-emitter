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
     * Explicit allowlist of origins that may receive credentialed CORS responses.
     *
     * @var list<string>
     */
    private array $allowedOrigins;

    /**
     * Whether the wildcard `"*"` was present in the supplied allowlist.
     *
     * When true, and no explicit origin match is found, `Access-Control-Allow-Origin: *`
     * is emitted **without** `Access-Control-Allow-Credentials` — the CORS specification
     * forbids credentials with a wildcard origin value.
     */
    private bool $wildcardAllowed;

    /**
     * @param list<string> $allowedOrigins Explicit allowlist of accepted request origins.
     *                                     May include `"*"` to permit any origin without credentials.
     * @param int $responseChunkSize Maximum body chunk size emitted per iteration.
     */
    public function __construct(array $allowedOrigins = [], int $responseChunkSize = 4096)
    {
        $normalized = array_unique(array_filter(
            array_map('trim', $allowedOrigins),
            static fn (string $origin): bool => $origin !== ''
        ));

        $this->wildcardAllowed = in_array('*', $normalized, true);

        $this->allowedOrigins = array_values(array_filter(
            $normalized,
            static fn (string $origin): bool => $origin !== '*'
        ));

        parent::__construct($responseChunkSize);
    }

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
     * Returns a new response instance with validated CORS and no-cache headers.
     *
     * Resolution order:
     *  1. If the request origin matches an explicit allowlist entry, emit a credentialed
     *     response (`Access-Control-Allow-Origin: <origin>` + `Access-Control-Allow-Credentials: true`
     *     + `Vary: Origin`).
     *  2. If `"*"` was included in the allowlist and no explicit match was found, emit
     *     `Access-Control-Allow-Origin: *` **without** `Access-Control-Allow-Credentials`
     *     (the CORS specification forbids credentials with a wildcard origin value).
     *  3. Otherwise, `Access-Control-Allow-Origin` is omitted entirely.
     *
     * @param ResponseInterface $response The response to decorate with headers.
     *
     * @return ResponseInterface The decorated response instance.
     */
    protected function applyHeaders(ResponseInterface $response): ResponseInterface
    {
        $response = $response
            ->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization'
            )
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        if ($origin !== null && in_array($origin, $this->allowedOrigins, true)) {
            return $response
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withAddedHeader('Vary', 'Origin');
        }

        if ($this->wildcardAllowed) {
            return $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        return $response;
    }
}
