<?php

declare(strict_types=1);

namespace App\Form\Type\Onboarding;

use App\Form\Type\OnboardingType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<OnboardingType> */
final class ClassDetailsStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('className', TextType::class, [
                'label' => 'Nazwa (np. Klasa 4B)',
                'attr' => [
                    'placeholder' => '4B',
                ],
            ])
            ->add('schoolName', TextType::class, [
                'label' => 'Szkoła',
                'required' => false,
                'attr' => [
                    'placeholder' => 'SP nr 12 w Krakowie',
                ],
            ])
            ->add('blikPhone', TextType::class, [
                'label' => 'Numer telefonu do BLIK',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Numer telefonu do BLIK',
                ],
            ])
            ->add('accountNumber', TextType::class, [
                'label' => 'Numer konta bankowego (PL...)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Numer konta bankowego (PL...)',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'validation_groups' => ['class_details', 'Default'],
        ]);
    }
}
