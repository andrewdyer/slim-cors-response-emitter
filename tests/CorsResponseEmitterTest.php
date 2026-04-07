<?php

declare(strict_types=1);

namespace AndrewDyer\CorsResponseEmitter\Tests;

use AndrewDyer\CorsResponseEmitter\CorsResponseEmitter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Verifies CORS and cache header behavior during response emission.
 *
 * @internal
 */
final class CorsResponseEmitterTest extends TestCase
{
    /**
     * Resets request-origin state between tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);
    }

    /**
     * Ensures allowlisted origins receive credentialed CORS headers.
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
     * Ensures missing request origin omits origin-specific CORS headers.
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
     * Ensures non-allowlisted origins do not receive credentialed CORS headers.
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
     * Ensures stale buffered output is cleared before response emission.
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
     * Ensures emission succeeds with an active but empty output buffer.
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
     * Ensures only the innermost buffer is cleared when nested buffers are active.
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
     * Ensures any trusted origin in a multi-origin allowlist is accepted.
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
     * Ensures an empty allowlist never emits credentialed CORS headers.
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
     * Ensures wildcard allowlists emit `Access-Control-Allow-Origin: *` without credentials.
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
     * Ensures wildcard allowlists emit a wildcard origin without request-origin input.
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
     * Ensures explicit origin matches take precedence over wildcard allowlist entries.
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
 * Captures decorated responses without emitting output during tests.
 *
 * @internal
 */
final class TestCorsResponseEmitter extends CorsResponseEmitter
{
    /**
     * Captured response after header decoration.
     *
     * @var ResponseInterface|null
     */
    public ?ResponseInterface $capturedResponse = null;

    /**
     * Applies headers and stores the decorated response for assertions.
     *
     * @param ResponseInterface $response
     *
     * @return void
     */
    public function emit(ResponseInterface $response): void
    {
        $response = $this->applyHeaders($response);

        $this->capturedResponse = $response;
    }
}
