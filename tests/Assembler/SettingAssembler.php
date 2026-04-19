<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Setting;

final class SettingAssembler
{
    private string $key = 'app_name';

    /**
     * @var array<string, mixed>
     */
    private array $content = [
        'value' => 'ClassyCash',
    ];

    public static function new(): self
    {
        return new self();
    }

    public function withKey(string $key): self
    {
        $clone = clone $this;
        $clone->key = $key;

        return $clone;
    }

    public function withValue(mixed $value): self
    {
        $clone = clone $this;
        $clone->content = [
            'value' => $value,
        ];

        return $clone;
    }

    public function assemble(): Setting
    {
        $setting = new Setting();
        $setting->setKey($this->key);
        $setting->setContent($this->content);

        return $setting;
    }
}
