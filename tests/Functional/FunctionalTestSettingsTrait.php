<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Assembler\SettingAssembler;
use Doctrine\ORM\EntityManagerInterface;

trait FunctionalTestSettingsTrait
{
    private function setupDefaultSettings(EntityManagerInterface $em): void
    {
        $settings = [
            'app_name' => 'ClassyCash',
            'email_from' => 'noreply@example.com',
            'blik_phone' => '+48123456789',
            'transfer_account' => '12 3456 7890 1234 5678 9012 3456',
        ];

        foreach ($settings as $key => $value) {
            $em->persist(SettingAssembler::new() ->withKey($key) ->withValue($value) ->assemble());
        }

        $em->flush();
    }
}
