<?php

declare(strict_types=1);

namespace App\Application\Service;

class WebmailUrlService
{
    public function __construct(
        private readonly string $appEnv
    ) {}

    /**
     * @return array{url: string, name: string}
     */
    public function getWebmailInfo(string $email): array
    {
        if ($this->appEnv === 'dev') {
            return [
                'url' => 'http://127.0.0.1:8025',
                'name' => 'Mailpit',
            ];
        }

        $domain = explode('@', $email)[1] ?? '';

        // Check MX records
        $mxhosts = [];
        getmxrr($domain, $mxhosts);
        $mxString = implode(' ', $mxhosts);

        // Logic for webmail identification
        if (str_contains($mxString, 'google.com')) {
            return [
                'url' => 'https://mail.google.com',
                'name' => 'Gmail',
            ];
        } elseif (str_contains($mxString, 'outlook.com')) {
            return [
                'url' => 'https://office.com',
                'name' => 'Outlook',
            ];
        } elseif (str_contains($mxString, 'microsoft.com')) {
            return [
                'url' => 'https://office.com',
                'name' => 'Outlook',
            ];
        } elseif (str_contains($domain, 'onet.pl')) {
            return [
                'url' => 'https://poczta.onet.pl',
                'name' => 'Poczta Onet',
            ];
        } elseif (str_contains($domain, 'wp.pl')) {
            return [
                'url' => 'https://poczta.wp.pl',
                'name' => 'Poczta WP',
            ];
        } elseif (str_contains($domain, 'interia.pl')) {
            return [
                'url' => 'https://poczta.interia.pl',
                'name' => 'Poczta Interia',
            ];
        } elseif (str_contains($domain, 'o2.pl')) {
            return [
                'url' => 'https://poczta.o2.pl',
                'name' => 'Poczta O2',
            ];
        } elseif (str_contains($domain, 'zoho')) {
            return [
                'url' => 'https://mail.zoho.com',
                'name' => 'Zoho Mail',
            ];
        }

        // Default - try just the domain
        return [
            'url' => 'https://www.' . $domain,
            'name' => 'Webmail',
        ];
    }
}
