<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Form\Model\OnboardingDto;
use App\Form\Type\Onboarding\AutomationStepType;
use App\Form\Type\Onboarding\ClassDetailsStepType;
use App\Form\Type\Onboarding\IdentityStepType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\DataStorage\NullDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OnboardingType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder->addStep('identity', IdentityStepType::class, [
            'inherit_data' => true,
        ]);
        $builder->addStep('class_details', ClassDetailsStepType::class, [
            'inherit_data' => true,
        ]);
        $builder->addStep('automation', AutomationStepType::class, [
            'inherit_data' => true,
        ]);

        $builder->add('navigator', NavigatorFlowType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OnboardingDto::class,
            'step_property_path' => 'step',
            'data_storage' => new NullDataStorage(),
        ]);
    }
}
