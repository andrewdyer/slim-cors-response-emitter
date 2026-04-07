![Slim CORS Response Emitter](https://public-assets.andrewdyer.rocks/images/covers/cors-response-emitter.png)

<p align="center">
  <a href="https://packagist.org/packages/andrewdyer/cors-response-emitter"><img src="https://poser.pugx.org/andrewdyer/cors-response-emitter/v/stable?style=for-the-badge" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/andrewdyer/cors-response-emitter"><img src="https://poser.pugx.org/andrewdyer/cors-response-emitter/downloads?style=for-the-badge" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/andrewdyer/cors-response-emitter"><img src="https://poser.pugx.org/andrewdyer/cors-response-emitter/license?style=for-the-badge" alt="License"></a>
  <a href="https://packagist.org/packages/andrewdyer/cors-response-emitter"><img src="https://poser.pugx.org/andrewdyer/cors-response-emitter/require/php?style=for-the-badge" alt="PHP Version Required"></a>
</p>

# CORS Response Emitter

A CORS-aware response emitter for [Slim Framework](https://www.slimframework.com/) applications, designed to ensure consistent and secure HTTP responses.

## Introduction

This library emits HTTP responses with consistent CORS and cache-control headers. It validates the incoming `Origin` against an explicit allowlist and emits credentialed CORS headers only for trusted origins. For public APIs, wildcard origins may be used to allow cross-origin access without credentials.

## Prerequisites

- **[PHP](https://www.php.net/)**: Version 8.3 or higher is required.
- **[Composer](https://getcomposer.org/)**: Dependency management tool for PHP.

## Installation

```bash
composer require andrewdyer/cors-response-emitter
```

## Getting Started

The examples below demonstrate how to configure the emitter and emit a Slim response with CORS headers.

### 1. Configure trusted origins

Provide an allowlist of origins that may receive credentialed CORS responses.

```php
use AndrewDyer\CorsResponseEmitter\CorsResponseEmitter;

$emitter = new CorsResponseEmitter([
    'https://app.example.com',
    'https://admin.example.com',
]);
```

### 2. Emit the response

After Slim handles the request, pass the response to the emitter.

```php
$emitter->emit($response);
```

## 📚 Usage

The emitter resolves CORS headers from the request origin and allowlist configuration:

| Scenario                                           | `Access-Control-Allow-Origin`                     | `Access-Control-Allow-Credentials` | `Vary`      |
| -------------------------------------------------- | ------------------------------------------------- | ---------------------------------- | ----------- |
| Request origin matches an explicit allowlist entry | Reflected origin (e.g. `https://app.example.com`) | `true`                             | `Origin`    |
| `"*"` in allowlist, no explicit match              | `*`                                               | _(omitted)_                        | _(omitted)_ |
| No match and no wildcard allowlist entry           | _(omitted)_                                       | _(omitted)_                        | _(omitted)_ |

### Allow exact origins

Use explicit origins when endpoints need credentialed cross-origin requests.

```php
use AndrewDyer\CorsResponseEmitter\CorsResponseEmitter;

$emitter = new CorsResponseEmitter([
    'https://app.example.com',
    'https://admin.example.com',
]);
$emitter->emit($response);
```

### Allow any origin for public APIs

A wildcard origin (`"*"`) may be configured as an allowlist entry to permit requests from any origin. This is suitable for fully public, unauthenticated APIs:

```php
use AndrewDyer\CorsResponseEmitter\CorsResponseEmitter;

$emitter = new CorsResponseEmitter(['*']);
$emitter->emit($response);
```

### Combine exact and wildcard origins

Explicit origins and `"*"` may be combined. An exact match always takes precedence and receives the credentialed response. Requests from any other origin fall back to the uncredentialed wildcard response:

```php
use AndrewDyer\CorsResponseEmitter\CorsResponseEmitter;

$emitter = new CorsResponseEmitter([
    '*',
    'https://app.example.com', // receives credentialed response
]);
$emitter->emit($response);
```

Important: the [CORS specification](https://fetch.spec.whatwg.org/#cors-protocol-and-credentials) forbids sending `Access-Control-Allow-Credentials: true` with `Access-Control-Allow-Origin: *`. If an endpoint requires cookies, HTTP authentication, or client certificates, use explicit origins.

## Complete Example

The following example combines Slim setup, request handling, and CORS-aware response emission:

```php
<?php

declare(strict_types=1);

use AndrewDyer\CorsResponseEmitter\CorsResponseEmitter;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/vendor/autoload.php';

// Create the Slim application.
$app = AppFactory::create();

// Build a PSR-7 request from PHP globals.
$requestCreator = ServerRequestCreatorFactory::create();
$request = $requestCreator->createServerRequestFromGlobals();

// Handle the request and get a response.
$response = $app->handle($request);

// Emit the response with CORS headers.
$emitter = new CorsResponseEmitter([
    'https://app.example.com',
    'https://admin.example.com',
]);
$emitter->emit($response);
```

## License

Licensed under the [MIT license](https://opensource.org/licenses/MIT) and is free for private or commercial projects.
