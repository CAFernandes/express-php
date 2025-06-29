<?php

namespace Express\Tests\Core;

use PHPUnit\Framework\TestCase;
use Express\Middleware\Security\CorsMiddleware;

class CorsMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset global state
        $_SERVER = [];
    }

    public function testDefaultCorsConfiguration(): void
    {
        $middleware = new CorsMiddleware();
        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function testCorsHeadersWithDefaults(): void
    {
        $middleware = new CorsMiddleware();

        $request = (object) ['method' => 'GET'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
        $this->assertContains('Access-Control-Allow-Origin: *', $response->headers);
        $this->assertContains('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,PATCH,OPTIONS', $response->headers);
        $this->assertContains('Access-Control-Allow-Headers: Content-Type,Authorization', $response->headers);
    }

    public function testCorsWithCustomOptions(): void
    {
        $options = [
            'origin' => 'https://example.com',
            'methods' => 'GET,POST',
            'headers' => 'Content-Type,X-API-Key',
            'credentials' => true
        ];

        $middleware = new CorsMiddleware($options);

        $request = (object) ['method' => 'GET'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
        $this->assertContains('Access-Control-Allow-Origin: https://example.com', $response->headers);
        $this->assertContains('Access-Control-Allow-Methods: GET,POST', $response->headers);
        $this->assertContains('Access-Control-Allow-Headers: Content-Type,X-API-Key', $response->headers);
        $this->assertContains('Access-Control-Allow-Credentials: true', $response->headers);
    }

    public function testCorsWithAllowedOriginsList(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';

        $options = [
            'origin' => ['https://app.example.com', 'https://admin.example.com']
        ];

        $middleware = new CorsMiddleware($options);

        $request = (object) ['method' => 'GET'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
        $this->assertContains('Access-Control-Allow-Origin: https://app.example.com', $response->headers);
    }

    public function testCorsWithDisallowedOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://malicious.com';

        $options = [
            'origin' => ['https://app.example.com', 'https://admin.example.com']
        ];

        $middleware = new CorsMiddleware($options);

        $request = (object) ['method' => 'GET'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
        $this->assertContains('Access-Control-Allow-Origin: null', $response->headers);
    }

    public function testOptionsPreflightRequest(): void
    {
        $middleware = new CorsMiddleware();

        $request = (object) ['method' => 'OPTIONS'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $result = $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        // Verificar que next() não foi chamado (request OPTIONS finalizada)
        $this->assertFalse($nextCalled);

        // Verificar headers CORS
        $this->assertContains('Access-Control-Allow-Origin: *', $response->headers);
        $this->assertContains('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,PATCH,OPTIONS', $response->headers);
        $this->assertContains('Access-Control-Allow-Headers: Content-Type,Authorization', $response->headers);

        // Verificar status 204
        $this->assertContains('Status: 204', $response->status);
    }

    public function testCorsWithoutCredentials(): void
    {
        $options = ['credentials' => false];
        $middleware = new CorsMiddleware($options);

        $request = (object) ['method' => 'GET'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);

        // Should not contain credentials header when set to false
        $credentialsHeaders = array_filter($response->headers, function ($header) {
            return strpos($header, 'Access-Control-Allow-Credentials') !== false;
        });
        $this->assertEmpty($credentialsHeaders);
    }

    public function testCorsWithEmptyOriginArray(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $options = ['origin' => []];
        $middleware = new CorsMiddleware($options);

        $request = (object) ['method' => 'GET'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
        $this->assertContains('Access-Control-Allow-Origin: null', $response->headers);
    }

    public function testCorsWithoutHttpOriginHeader(): void
    {
        // No HTTP_ORIGIN set in $_SERVER
        $options = ['origin' => ['https://app.example.com']];
        $middleware = new CorsMiddleware($options);

        $request = (object) ['method' => 'GET'];
        $response = $this->createMockResponse();
        $nextCalled = false;

        $middleware($request, $response, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertTrue($nextCalled);
        $this->assertContains('Access-Control-Allow-Origin: null', $response->headers);
    }

    public function testCorsMethodChaining(): void
    {
        $middleware = new CorsMiddleware();

        $request = (object) ['method' => 'POST'];
        $response = $this->createMockResponse();

        $middlewareStack = [
            function ($req, $res, $next) {
                $req->test1 = true;
                $next();
            },
            $middleware,
            function ($req, $res, $next) {
                $req->test2 = true;
                $next();
            }
        ];

        $this->executeMiddlewareStack($middlewareStack, $request, $response);

        $this->assertTrue($request->test1);
        $this->assertTrue($request->test2);
    }

    /**
     * Create a mock response object for testing
     */
    private function createMockResponse()
    {
        return new class {
            public $headers = [];
            public $status = [];

            public function header($name, $value)
            {
                $this->headers[] = "$name: $value";
                return $this;
            }

            public function status($code)
            {
                $this->status[] = "Status: $code";
                return $this;
            }

            public function end()
            {
                return $this;
            }
        };
    }

    /**
     * Execute a middleware stack for testing
     */
    private function executeMiddlewareStack($middlewares, $request, $response)
    {
        $index = 0;
        $next = function () use (&$index, $middlewares, $request, $response, &$next) {
            if ($index < count($middlewares)) {
                $middleware = $middlewares[$index++];
                $middleware($request, $response, $next);
            }
        };
        $next();
    }
}
