<?php

namespace App\Tests\Service;

use App\Service\LinkMobilityClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class LinkMobilityClientTest extends TestCase
{
    private const API_KEY = 'test-api-key';
    private const API_SECRET = 'test-api-secret';
    private const API_URL = 'https://api-test.msghub.cloud';
    private const SENDER = 'TestSender';

    public function testSuccessfulSendReturnsMsgId(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'meta' => ['code' => 200, 'text' => 'OK'],
            'data' => ['msg_id' => 'light-abc123'],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new LinkMobilityClient($httpClient, self::API_KEY, self::API_SECRET, self::API_URL, self::SENDER);

        $result = $client->send('+33612345678', 'Hello test', 42);

        $this->assertSame('light-abc123', $result['msg_id']);
    }

    public function testRequestFormatAndHmacSignature(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'meta' => ['code' => 200, 'text' => 'OK'],
            'data' => ['msg_id' => 'test-id'],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new LinkMobilityClient($httpClient, self::API_KEY, self::API_SECRET, self::API_URL, self::SENDER);

        $client->send('+33612345678', 'Test message', 10);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://api-test.msghub.cloud/send', $mockResponse->getRequestUrl());

        $body = $mockResponse->getRequestOptions()['body'];
        $decoded = json_decode($body, true);

        $this->assertSame('+33612345678', $decoded['msisdn']);
        $this->assertSame(self::SENDER, $decoded['sc']);
        $this->assertSame('Test message', $decoded['text']);
        $this->assertSame(10, $decoded['service_id']);
        $this->assertTrue($decoded['registered_delivery']);

        // Verify HMAC signature
        $expectedSignature = hash_hmac('sha512', $body, self::API_SECRET);
        $headers = $mockResponse->getRequestOptions()['headers'];

        $signHeader = null;
        $keyHeader = null;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'x-api-sign:')) {
                $signHeader = trim(substr($header, strlen('x-api-sign:')));
            }
            if (str_starts_with($header, 'x-api-key:')) {
                $keyHeader = trim(substr($header, strlen('x-api-key:')));
            }
        }

        $this->assertSame($expectedSignature, $signHeader);
        $this->assertSame(self::API_KEY, $keyHeader);
    }

    public function testExtraParametersAreMerged(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'meta' => ['code' => 200, 'text' => 'OK'],
            'data' => ['msg_id' => 'test-id'],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new LinkMobilityClient($httpClient, self::API_KEY, self::API_SECRET, self::API_URL, self::SENDER);

        $client->send('+33612345678', 'Test', 10, ['priority' => 512]);

        $body = $mockResponse->getRequestOptions()['body'];
        $decoded = json_decode($body, true);

        $this->assertSame(512, $decoded['priority']);
    }

    public function testErrorResponseThrowsRuntimeException(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'meta' => ['code' => 401, 'text' => 'Unauthorized'],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new LinkMobilityClient($httpClient, self::API_KEY, self::API_SECRET, self::API_URL, self::SENDER);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LINK Mobility API error 401: Unauthorized');

        $client->send('+33612345678', 'Test', 10);
    }

    public function testMissingMetaCodeThrowsRuntimeException(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'meta' => ['text' => 'Something wrong'],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $client = new LinkMobilityClient($httpClient, self::API_KEY, self::API_SECRET, self::API_URL, self::SENDER);

        $this->expectException(\RuntimeException::class);

        $client->send('+33612345678', 'Test', 10);
    }
}
