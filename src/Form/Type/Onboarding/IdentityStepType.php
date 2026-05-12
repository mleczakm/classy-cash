<?php

declare(strict_types=1);

namespace App\Form\Type\Onboarding;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class IdentityStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Imię',
                'attr' => [
                    'placeholder' => 'np. Anna',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nazwisko',
                'attr' => [
                    'placeholder' => 'np. Kowalska',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail (Właściciel)',
                'attr' => [
                    'placeholder' => 'twoj@email.com',
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Hasło do panelu',
                'attr' => [
                    'placeholder' => 'Ustal bezpieczne hasło',
                ],
                'always_empty' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'validation_groups' => ['identity', 'Default'],
        ]);
    }
}
