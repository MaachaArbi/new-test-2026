<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Preflight CORS (nelmio/cors-bundle) — origines explicites via CORS_ALLOW_ORIGIN.
 */
final class CorsPreflightTest extends WebTestCase
{
    private const ALLOWED_ORIGIN = 'http://localhost:5173';

    private const DISALLOWED_ORIGIN = 'https://evil.example';

    public function test_preflight_allowed_origin_receives_cors_headers(): void
    {
        $client = static::createClient();

        $client->request(
            'OPTIONS',
            '/api/v1/auth/login',
            server: [
                'HTTP_ORIGIN' => self::ALLOWED_ORIGIN,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, authorization',
            ],
        );

        $response = $client->getResponse();
        self::assertLessThan(400, $response->getStatusCode());

        self::assertSame(
            self::ALLOWED_ORIGIN,
            $response->headers->get('Access-Control-Allow-Origin'),
        );

        $allowMethods = strtoupper((string) $response->headers->get('Access-Control-Allow-Methods'));
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            self::assertStringContainsString($method, $allowMethods);
        }

        $allowHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        self::assertStringContainsString('content-type', $allowHeaders);
        self::assertStringContainsString('authorization', $allowHeaders);
    }

    public function test_preflight_disallowed_origin_does_not_receive_allow_origin(): void
    {
        $client = static::createClient();

        $client->request(
            'OPTIONS',
            '/api/v1/auth/login',
            server: [
                'HTTP_ORIGIN' => self::DISALLOWED_ORIGIN,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, authorization',
            ],
        );

        $response = $client->getResponse();

        self::assertFalse(
            $response->headers->has('Access-Control-Allow-Origin'),
            'Une origine non listée dans CORS_ALLOW_ORIGIN ne doit pas recevoir Access-Control-Allow-Origin.',
        );
        self::assertNotSame(
            self::DISALLOWED_ORIGIN,
            $response->headers->get('Access-Control-Allow-Origin'),
        );
        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
