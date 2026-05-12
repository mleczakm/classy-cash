<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class OnboardingDto
{
    public string $step = 'identity';

    #[Assert\NotBlank(message: 'Imię jest wymagane', groups: ['identity'])]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Nazwisko jest wymagane', groups: ['identity'])]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'Email jest wymagany', groups: ['identity'])]
    #[Assert\Email(message: 'Nieprawidłowy adres email', groups: ['identity'])]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Hasło jest wymagane', groups: ['identity'])]
    #[Assert\Length(min: 8, minMessage: 'Hasło musi mieć co najmniej {{ limit }} znaków', groups: ['identity'])]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'Nazwa klasy jest wymagana', groups: ['class_details'])]
    public ?string $className = null;

    public ?string $schoolName = null;

    public ?string $blikPhone = null;

    public ?string $accountNumber = null;

    public ?string $automationEmail = null;

    public ?string $automationPassword = null;
}
