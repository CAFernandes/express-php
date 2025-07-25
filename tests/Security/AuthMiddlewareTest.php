<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Express\Http\Psr15\Middleware\AuthMiddleware;
use Express\Authentication\JWTHelper;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Express\Http\Psr7\Response;
use Express\Http\Psr7\ServerRequest;
use Tests\Security\MockResponse;
use Tests\Security\DummyHandler;

class AuthMiddlewareTest extends TestCase
{
    private $jwtSecret = 'test_secret_key_123';

    protected function setUp(): void
    {
        // Reset global state
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];

        // Simular ambiente web mínimo
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTPS'] = 'off';
    }

    private function createPsrRequest(array $headers = [], array $query = []): ServerRequestInterface
    {
        $request = new ServerRequest('GET', '/test');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function testJwtAuth(): void
    {
        $middleware = new AuthMiddleware(
            [
                'jwtSecret' => $this->jwtSecret,
                'authMethods' => ['jwt']
            ]
        );
        $payload = ['user_id' => 1, 'username' => 'testuser'];
        $token = JWTHelper::encode($payload, $this->jwtSecret);
        $request = $this->createPsrRequest(['Authorization' => "Bearer $token"]);
        $handler = new DummyHandler();
        $response = $middleware->process($request, $handler);
        $this->assertTrue($handler->called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testJWTAuthenticationFailure(): void
    {
        $middleware = new AuthMiddleware(
            [
                'jwtSecret' => $this->jwtSecret,
                'authMethods' => ['jwt']
            ]
        );
        $request = $this->createPsrRequest(['Authorization' => 'Bearer invalid_token']);
        $handler = new DummyHandler();
        $response = $middleware->process($request, $handler);
        $this->assertFalse($handler->called);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBasicAuth(): void
    {
        $basicCallback = function ($username, $password) {
            return ($username === 'admin' && $password === 'senha123') ? ['username' => $username] : false;
        };
        $middleware = new AuthMiddleware(
            [
                'authMethods' => ['basic'],
                'basicAuthCallback' => $basicCallback
            ]
        );
        $auth = base64_encode('admin:senha123');
        $request = $this->createPsrRequest(['Authorization' => 'Basic ' . $auth]);
        $handler = new DummyHandler();
        $response = $middleware->process($request, $handler);
        $this->assertTrue($handler->called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBasicAuthenticationFailure(): void
    {
        $basicCallback = function ($username, $password) {
            return $username === 'testuser' && $password === 'testpass' ? ['username' => $username] : false;
        };
        $middleware = new AuthMiddleware(
            [
                'authMethods' => ['basic'],
                'basicAuthCallback' => $basicCallback
            ]
        );
        $auth = base64_encode('testuser:wrongpass');
        $request = $this->createPsrRequest(['Authorization' => 'Basic ' . $auth]);
        $handler = new DummyHandler();
        $response = $middleware->process($request, $handler);
        $this->assertFalse($handler->called);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBearerAuth(): void
    {
        $bearerCallback = function ($token) {
            return ($token === 'valid_token') ? ['token' => $token] : false;
        };
        $middleware = new AuthMiddleware(
            [
                'authMethods' => ['bearer'],
                'bearerAuthCallback' => $bearerCallback
            ]
        );
        $request = $this->createPsrRequest(['Authorization' => 'Bearer valid_token']);
        $handler = new DummyHandler();
        $response = $middleware->process($request, $handler);
        $this->assertTrue($handler->called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCustomAuth(): void
    {
        $customAuth = function ($request) {
            return true;
        };
        $middleware = new AuthMiddleware(
            [
                'authMethods' => ['custom'],
                'customAuthCallback' => $customAuth
            ]
        );
        $request = $this->createPsrRequest(['customAuth' => 'valid']);
        $handler = new DummyHandler();
        $response = $middleware->process($request, $handler);
        $this->assertTrue($handler->called);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
