<?php

declare(strict_types=1);

namespace App\Settings;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class Settings
{
    public function __construct(
        private SettingRepository $repository,
        private EntityManagerInterface $em
    ) {}

    public function get(string $key): mixed
    {
        $setting = $this->repository->findOneByKey($key);
        if ($setting === null) {
            throw new \LogicException(sprintf('Setting "%s" is missing in database.', $key));
        }

        return $setting->getContent()['value'] ?? throw new \LogicException(sprintf(
            'Setting "%s" has no value in database.',
            $key
        ));
    }

    public function set(string $key, mixed $value): void
    {
        $setting = $this->repository->findOneByKey($key);
        if ($setting === null) {
            $setting = new Setting();
            $setting->setKey($key);
            $this->em->persist($setting);
        }

        $setting->setContent([
            'value' => $value,
        ]);
        $this->em->flush();
    }

    public function getEmailFrom(): string
    {
        $value = $this->get('email_from');
        if (! is_string($value)) {
            throw new \LogicException('Setting "email_from" must be a string.');
        }

        return $value;
    }

    public function getBlikPhone(): string
    {
        $value = $this->get('blik_phone');
        if (! is_string($value)) {
            throw new \LogicException('Setting "blik_phone" must be a string.');
        }

        return $value;
    }

    public function getTransferAccount(): string
    {
        $value = $this->get('transfer_account');
        if (! is_string($value)) {
            throw new \LogicException('Setting "transfer_account" must be a string.');
        }

        return $value;
    }

    public function getName(): string
    {
        $value = $this->get('app_name');
        if (! is_string($value)) {
            throw new \LogicException('Setting "app_name" must be a string.');
        }

        return $value;
    }
}
