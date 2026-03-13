## 🚀 Getting Started

These sections demonstrate how to initialize a Slim application and handle HTTP responses, including applying CORS and cache-control headers before sending the response to the client.

### App Setup

A `bootstrap/app.php` file needs to be created to initialize the Slim application and handle incoming requests. The following steps demonstrate the required setup. For a full working example, see [Complete starter example](#complete-starter-example) below.

#### Initialize the application

Create the Slim application instance using the `Slim\Factory\AppFactory` class. This provides the foundation for routing, middleware, and handling requests.

```php
$app = AppFactory::create();
```

#### Create a PSR-7 request

Create a PSR-7 request object from PHP globals using the `Slim\Factory\ServerRequestCreatorFactory` class. This ensures compatibility with the Slim application and middleware.

```php
$requestCreator = ServerRequestCreatorFactory::create();
$request = $requestCreator->createServerRequestFromGlobals();
```

#### Handle the request

Process the incoming request through the Slim application to produce a PSR-7 response.

```php
$response = $app->handle($request);
```

### Add CORS and cache-control headers

Before sending the response to the client, the HTTP response should include the appropriate CORS and cache-control headers. The `YourVendor\YourPackage\Http\CorsResponseEmitter` class validates the request `Origin` against an explicit allowlist, emits credentialed CORS headers only for allowed origins, and then emits the response.

```php
$emitter = new CorsResponseEmitter([
	'https://app.example.com',
	'https://admin.example.com',
]);
$emitter->emit($response);
```

#### Origin resolution

The emitter applies the following resolution order on each request:

| Scenario | `Access-Control-Allow-Origin` | `Access-Control-Allow-Credentials` | `Vary` |
|---|---|---|---|
| Request origin matches an explicit allowlist entry | Reflected origin (e.g. `https://app.example.com`) | `true` | `Origin` |
| `"*"` in allowlist, no explicit match | `*` | _(omitted)_ | _(omitted)_ |
| Origin missing or not in allowlist | _(omitted)_ | _(omitted)_ | _(omitted)_ |

> **Note:** The [CORS specification](https://fetch.spec.whatwg.org/#cors-protocol-and-credentials) forbids sending `Access-Control-Allow-Credentials: true` alongside `Access-Control-Allow-Origin: *`. When `"*"` is used, credentialed requests (those carrying cookies, HTTP authentication, or TLS client certificates) will be rejected by the browser. Use explicit origins for any endpoint that requires credentials.

#### Wildcard origin

Pass `"*"` as an allowlist entry to permit requests from any origin. This is suitable for fully public, unauthenticated APIs:

```php
$emitter = new CorsResponseEmitter(['*']);
$emitter->emit($response);
```

#### Mixed allowlist

Explicit origins and `"*"` may be combined. An exact match always takes precedence, receiving the credentialed response. Requests from any other origin fall back to the uncredentialed wildcard response:

```php
$emitter = new CorsResponseEmitter([
	'*',
	'https://app.example.com', // receives credentialed response
]);
$emitter->emit($response);
```

### Complete starter example

The following `bootstrap/app.php` demonstrates a fully working setup:

```php
<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use YourVendor\YourPackage\Http\CorsResponseEmitter;

require __DIR__ . '/../vendor/autoload.php';

// Initialize the Slim application
$app = AppFactory::create();

// Create a PSR-7 request from PHP globals
$requestCreator = ServerRequestCreatorFactory::create();
$request = $requestCreator->createServerRequestFromGlobals();

// Handle the request and produce a response
$response = $app->handle($request);

// Emit the response with CORS/cache headers; use '*' for public APIs or explicit origins for credentialed access
$emitter = new CorsResponseEmitter([
	'https://app.example.com',
	'https://admin.example.com',
]);
$emitter->emit($response);
```
