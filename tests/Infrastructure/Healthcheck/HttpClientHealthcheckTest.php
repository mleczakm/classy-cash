<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Healthcheck;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use SwooleBundle\Observability\HealthCheck\HttpClientHealthCheck;

#[Group('unit')]
final class HttpClientHealthcheckTest extends TestCase
{
    private const string URL = 'https://connectivitycheck.gstatic.com/generate_204';

    public function testAcceptsNoContentResponse(): void
    {
        $response = new HttpClientHealthCheck(
            new MockHttpClient(new MockResponse('', [
                'http_code' => 204,
            ])),
            self::URL,
        )->check();

        static::assertTrue($response->getResult());
        static::assertSame(204, $response->getParams()['status_code']);
    }

    public function testRejectsUnexpectedStatusAndTransportFailure(): void
    {
        $unexpected = new HttpClientHealthCheck(
            new MockHttpClient(new MockResponse('', [
                'http_code' => 503,
            ])),
            self::URL,
        )->check();
        $failed = new HttpClientHealthCheck(
            new MockHttpClient(static function (): never {
                throw new TransportException('network unavailable');
            }),
            self::URL,
        )->check();

        static::assertFalse($unexpected->getResult());
        static::assertSame(503, $unexpected->getParams()['status_code']);
        static::assertFalse($failed->getResult());
        static::assertStringContainsString('network unavailable', $failed->getMessage());
    }
}
