<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Form\Model\SettingsDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<SettingsDto> */
final class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'input-elegant w-full';

        $builder
            ->add('appName', TextType::class, [
                'label' => 'admin.settings.app_name.label',
                'help' => 'admin.settings.app_name.help',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Classy Cash',
                ],
            ])
            ->add('schoolName', TextType::class, [
                'label' => 'admin.settings.school_name.label',
                'help' => 'admin.settings.school_name.help',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Szkoła Podstawowa nr 1',
                ],
            ])
            ->add('emailFrom', EmailType::class, [
                'label' => 'admin.settings.email_from.label',
                'help' => 'admin.settings.email_from.help',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'noreply@classy-cash.com',
                ],
            ])
            ->add('blikPhone', TelType::class, [
                'label' => 'admin.settings.blik_phone.label',
                'help' => 'admin.settings.blik_phone.help',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => '+48 123 456 789',
                ],
            ])
            ->add('transferAccount', TextType::class, [
                'label' => 'admin.settings.transfer_account.label',
                'help' => 'admin.settings.transfer_account.help',
                'required' => false,
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'PL 12 3456 7890 1234 5678 9012 3456',
                ],
            ])
            ->add('parentRegistrationEnabled', CheckboxType::class, [
                'label' => 'admin.settings.parent_registration_enabled.label',
                'help' => 'admin.settings.parent_registration_enabled.help',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'admin.settings.save_button',
                'attr' => [
                    'class' => 'btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SettingsDto::class,
            'translation_domain' => 'messages',
        ]);
    }
}
