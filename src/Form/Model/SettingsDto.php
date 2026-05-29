<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class SettingsDto
{
    #[Assert\NotBlank]
    public string $appName = '';

    public string $schoolName = '';

    #[Assert\Email]
    public string $emailFrom = '';

    public string $blikPhone = '';

    public string $transferAccount = '';

    public bool $parentRegistrationEnabled = true;
}
