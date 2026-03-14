<?php

declare(strict_types=1);

namespace YourVendor\YourPackage\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;
use YourVendor\YourPackage\CorsResponseEmitter;

/**
 * Verifies CORS/cache headers are applied before response emission.
 *
 * Ensures credentialed CORS headers are only emitted for explicitly allowed origins.
 */
final class CorsResponseEmitterTest extends TestCase
{
    /**
     * Clears request-origin global state after each test.
     *
     * Prevents global state from leaking between tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);
    }

    /**
     * Applies CORS/cache headers when the current request origin is allowlisted
     * and asserts all expected headers are correctly applied.
     *
     * @return void
     */
    public function testEmitAddsCorsHeadersForAllowedRequestOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $emitter = new TestCorsResponseEmitter(['https://example.com']);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;

        $this->assertNotNull($captured);
        $this->assertSame('true', $captured->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame('https://example.com', $captured->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame(
            'X-Requested-With, Content-Type, Accept, Origin, Authorization',
            $captured->getHeaderLine('Access-Control-Allow-Headers')
        );
        $this->assertSame('GET, POST, PUT, PATCH, DELETE, OPTIONS', $captured->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('no-store, no-cache, must-revalidate, max-age=0', $captured->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('post-check=0, pre-check=0', $captured->getHeaderLine('Cache-Control'));
        $this->assertSame('no-cache', $captured->getHeaderLine('Pragma'));
        $this->assertSame('Origin', $captured->getHeaderLine('Vary'));
    }

    /**
     * Omits allow-origin and credentials headers when no request origin is available.
     *
     * @return void
     */
    public function testEmitOmitsAllowOriginWhenRequestOriginMissing(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);

        $emitter = new TestCorsResponseEmitter(['https://example.com']);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;

        $this->assertNotNull($captured);
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Credentials'));
        $this->assertFalse($captured->hasHeader('Vary'));
    }

    /**
     * Omits allow-origin and credentials headers when request origin is not allowlisted.
     *
     * @return void
     */
    public function testEmitOmitsAllowOriginWhenRequestOriginIsNotAllowed(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://untrusted.example';

        $emitter = new TestCorsResponseEmitter(['https://example.com']);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;

        $this->assertNotNull($captured);
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Credentials'));
        $this->assertFalse($captured->hasHeader('Vary'));
    }

    /**
     * Clears any stale output buffer content before emitting the response
     * and asserts the response body is output without the buffered content.
     *
     * @return void
     */
    public function testEmitClearsNonEmptyOutputBuffer(): void
    {
        $response = (new ResponseFactory())->createResponse(200);
        $response->getBody()->write('response-body');

        $emitter = new CorsResponseEmitter();

        $initialLevel = ob_get_level();
        ob_start();
        echo 'stale-buffered-content';

        $emitter->emit($response);

        $output = ob_get_contents();
        ob_end_clean();

        $this->assertSame($initialLevel, ob_get_level());
        $this->assertStringNotContainsString('stale-buffered-content', $output);
        $this->assertSame('response-body', $output);
    }

    /**
     * Handles an active but empty output buffer without error
     * and asserts the response body is emitted correctly.
     *
     * @return void
     */
    public function testEmitHandlesEmptyOutputBuffer(): void
    {
        $response = (new ResponseFactory())->createResponse(200);
        $response->getBody()->write('response-body');

        $emitter = new CorsResponseEmitter();

        $initialLevel = ob_get_level();
        ob_start();

        $emitter->emit($response);

        $output = ob_get_contents();
        ob_end_clean();

        $this->assertSame($initialLevel, ob_get_level());
        $this->assertSame('response-body', $output);
    }

    /**
     * Clears only the innermost output buffer when nested buffers are active
     * and asserts outer buffer content is preserved while stale inner content is removed.
     *
     * @return void
     */
    public function testEmitClearsCurrentBufferWithoutAffectingOuterBuffer(): void
    {
        $response = (new ResponseFactory())->createResponse(200);
        $response->getBody()->write('response-body');

        $emitter = new CorsResponseEmitter();

        $initialLevel = ob_get_level();

        ob_start();
        echo 'outer-buffer-';

        ob_start();
        echo 'inner-buffer-should-be-cleared';

        $emitter->emit($response);

        $innerBufferOutput = ob_get_contents();
        ob_end_flush(); // flush inner buffer to outer

        $outerBufferOutput = ob_get_contents();
        ob_end_clean();

        $this->assertSame($initialLevel, ob_get_level());
        $this->assertStringNotContainsString('inner-buffer-should-be-cleared', $innerBufferOutput);
        $this->assertSame('response-body', $innerBufferOutput);
        $this->assertStringContainsString('outer-buffer-response-body', $outerBufferOutput);
        $this->assertStringNotContainsString('inner-buffer-should-be-cleared', $outerBufferOutput);
    }

    /**
     * Allows multiple origins to be allowlisted and verifies they are recognized.
     *
     * @return void
     */
    public function testEmitAddsCorsHeadersForMultipleAllowedOrigins(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://trusted.example';

        $allowed = [
            'https://example.com',
            'https://trusted.example',
            'https://another.example',
        ];

        $emitter = new TestCorsResponseEmitter($allowed);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;
        $this->assertNotNull($captured);
        $this->assertSame('true', $captured->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame('https://trusted.example', $captured->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $captured->getHeaderLine('Vary'));
    }

    /**
     * Ensures that an empty allowlist always prevents emitting CORS credentials,
     * even if $_SERVER['HTTP_ORIGIN'] is set.
     *
     * @return void
     */
    public function testEmitWithEmptyAllowlistOmitsCorsCredentials(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $emitter = new TestCorsResponseEmitter([]);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;
        $this->assertNotNull($captured);
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Credentials'));
        $this->assertFalse($captured->hasHeader('Vary'));
    }

    /**
     * Emits `Access-Control-Allow-Origin: *` without credentials when `"*"` is the
     * only allowlist entry, satisfying the CORS spec restriction.
     *
     * @return void
     */
    public function testEmitWithWildcardAllowlistSetsWildcardOriginWithoutCredentials(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://any.example';

        $emitter = new TestCorsResponseEmitter(['*']);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;
        $this->assertNotNull($captured);
        $this->assertSame('*', $captured->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Credentials'));
        $this->assertFalse($captured->hasHeader('Vary'));
    }

    /**
     * Emits `Access-Control-Allow-Origin: *` even when no request origin header is present,
     * since the wildcard is a static, unconditional value.
     *
     * @return void
     */
    public function testEmitWithWildcardAllowlistSetsWildcardOriginWhenRequestOriginMissing(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);

        $emitter = new TestCorsResponseEmitter(['*']);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;
        $this->assertNotNull($captured);
        $this->assertSame('*', $captured->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertFalse($captured->hasHeader('Access-Control-Allow-Credentials'));
        $this->assertFalse($captured->hasHeader('Vary'));
    }

    /**
     * Explicit origin match takes precedence over the wildcard entry.
     *
     * When `"*"` and specific origins are both in the allowlist, a request whose
     * origin matches a specific entry should receive the credentialed response
     * (`Access-Control-Allow-Origin: <origin>` + `Access-Control-Allow-Credentials: true`).
     *
     * @return void
     */
    public function testEmitPrefersExplicitOriginOverWildcard(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $emitter = new TestCorsResponseEmitter(['*', 'https://example.com']);
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;
        $this->assertNotNull($captured);
        $this->assertSame('https://example.com', $captured->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $captured->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame('Origin', $captured->getHeaderLine('Vary'));
    }
}

/**
 * Test helper emitter that captures the decorated response without output.
 *
 * This avoids sending headers/output during unit tests while allowing
 * assertions against the modified response instance.
 */
final class TestCorsResponseEmitter extends CorsResponseEmitter
{
    /**
     * The response instance after header decoration.
     *
     * @var ResponseInterface|null
     */
    public ?ResponseInterface $capturedResponse = null;

    /**
     * {@inheritDoc}
     */
    public function emit(ResponseInterface $response): void
    {
        $response = $this->applyHeaders($response);

        $this->capturedResponse = $response;
    }
}
