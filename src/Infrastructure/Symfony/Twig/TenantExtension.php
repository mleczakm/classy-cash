<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Twig;

use App\Settings\Settings;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TenantExtension extends AbstractExtension
{
    public function __construct(
        private readonly Settings $settings
    ) {}

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant_phone', $this->phone(...)),
            new TwigFunction('tenant_account', $this->account(...)),
            new TwigFunction('tenant_name', $this->name(...)),
            new TwigFunction('tenant_email_from', $this->emailFrom(...)),
        ];
    }

    public function phone(): string
    {
        return $this->settings->getBlikPhone();
    }

    public function account(): string
    {
        return $this->settings->getTransferAccount();
    }

    public function name(): string
    {
        return $this->settings->getName();
    }

    public function emailFrom(): string
    {
        return $this->settings->getEmailFrom();
    }
}
