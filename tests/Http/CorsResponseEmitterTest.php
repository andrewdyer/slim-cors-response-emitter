<?php

declare(strict_types=1);

namespace YourVendor\YourPackage\Tests\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;
use YourVendor\YourPackage\Http\CorsResponseEmitter;

/**
 * Verifies CORS/cache headers are applied before response emission.
 *
 * Ensures all responses emitted include consistent CORS and cache headers.
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
     * Applies CORS/cache headers using the current request origin
     * and asserts all headers are correctly applied.
     *
     * @return void
     */
    public function testEmitAddsCorsHeadersUsingRequestOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $emitter = new TestCorsResponseEmitter();
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
    }

    /**
     * Uses an empty allow-origin value when no request origin is available
     * and asserts the Access-Control-Allow-Origin header is empty.
     *
     * @return void
     */
    public function testEmitSetsEmptyAllowOriginWhenRequestOriginMissing(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);

        $emitter = new TestCorsResponseEmitter();
        $response = (new ResponseFactory())->createResponse(200);

        $emitter->emit($response);

        $captured = $emitter->capturedResponse;

        $this->assertNotNull($captured);
        $this->assertSame('', $captured->getHeaderLine('Access-Control-Allow-Origin'));
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
