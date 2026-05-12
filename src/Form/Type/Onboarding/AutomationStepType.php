<?php

declare(strict_types=1);

namespace App\Form\Type\Onboarding;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AutomationStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('automationEmail', EmailType::class, [
                'label' => 'Email do automatyzacji',
                'required' => false,
                'attr' => [
                    'placeholder' => 'TwojaSkrzynka@gmail.com',
                ],
            ])
            ->add('automationPassword', PasswordType::class, [
                'label' => 'Hasło aplikacji (16 znaków)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Hasło aplikacji (16 znaków)',
                ],
                'always_empty' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'validation_groups' => ['automation', 'Default'],
        ]);
    }
}
