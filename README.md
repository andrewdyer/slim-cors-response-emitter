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

Before sending the response to the client, the HTTP response should include the appropriate CORS and cache-control headers. The `YourVendor\YourPackage\Http\CorsResponseEmitter` class decorates the response with these headers and then emits it.

```php
$emitter = new CorsResponseEmitter();
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

// Emit the response with CORS and cache-control headers applied
$emitter = new CorsResponseEmitter();
$emitter->emit($response);
```
