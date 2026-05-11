<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use PHPUnit\Framework\Attributes\Group;
use App\Application\Service\WebmailUrlService;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class WebmailUrlServiceTest extends TestCase
{
    private WebmailUrlService $devService;

    private WebmailUrlService $prodService;

    protected function setUp(): void
    {
        $this->devService = new WebmailUrlService('dev');
        $this->prodService = new WebmailUrlService('prod');
    }

    public function testReturnsMailpitForDevEnvironment(): void
    {
        $result = $this->devService->getWebmailInfo('test@example.com');

        $this->assertEquals([
            'url' => 'http://127.0.0.1:8025',
            'name' => 'Mailpit',
        ], $result);
    }

    public function testReturnsOnetForOnetDomain(): void
    {
        $result = $this->prodService->getWebmailInfo('test@onet.pl');

        $this->assertEquals([
            'url' => 'https://poczta.onet.pl',
            'name' => 'Poczta Onet',
        ], $result);
    }

    public function testReturnsWpForWpDomain(): void
    {
        $result = $this->prodService->getWebmailInfo('test@wp.pl');

        $this->assertEquals([
            'url' => 'https://poczta.wp.pl',
            'name' => 'Poczta WP',
        ], $result);
    }

    public function testReturnsInteriaForInteriaDomain(): void
    {
        $result = $this->prodService->getWebmailInfo('test@interia.pl');

        $this->assertEquals([
            'url' => 'https://poczta.interia.pl',
            'name' => 'Poczta Interia',
        ], $result);
    }

    public function testReturnsO2ForO2Domain(): void
    {
        $result = $this->prodService->getWebmailInfo('test@o2.pl');

        $this->assertEquals([
            'url' => 'https://poczta.o2.pl',
            'name' => 'Poczta O2',
        ], $result);
    }

    public function testReturnsDefaultForUnknownDomain(): void
    {
        $result = $this->prodService->getWebmailInfo('test@unknown-domain.com');

        $this->assertEquals([
            'url' => 'https://www.unknown-domain.com',
            'name' => 'Webmail',
        ], $result);
    }

    public function testReturnsArrayWithUrlAndNameKeys(): void
    {
        $result = $this->prodService->getWebmailInfo('test@example.com');

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('name', $result);
    }
}
