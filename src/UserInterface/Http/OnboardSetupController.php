<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\ClassCouncil\ClassRoom;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/setup')]
#[IsGranted('ROLE_USER')]
final class OnboardSetupController extends AbstractController
{
    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'onboard_setup', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        // Check if setup is already completed
        if ($this->classRooms->count() > 0) {
            return $this->redirectToRoute('treasurer_dashboard');
        }

        $form = $this->createSetupForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Create class room
            $classRoom = new ClassRoom($data['className']);
            $this->em->persist($classRoom);

            // Save settings
            $this->settings->set('app_name', $data['schoolName']);
            $this->settings->set('blik_phone', $data['blikPhone']);
            $this->settings->set('transfer_account', $data['bankAccount']);
            $this->settings->set('setup_email', $data['email']);
            $this->settings->set('setup_password', $data['appPassword']);

            $this->em->flush();

            $this->addFlash('success', 'System został pomyślnie skonfigurowany');
            return $this->redirectToRoute('treasurer_dashboard');
        }

        return $this->render('onboard_setup/index.html.twig', [
            'form' => $form,
            'step' => $request->query->get('step', 1),
        ]);
    }

    private function createSetupForm(): FormInterface
    {
        return $this->createFormBuilder()
            ->add('className', TextType::class, [
                'label' => 'Nazwa klasy',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Nazwa klasy jest wymagana',
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Nazwa klasy musi mieć co najmniej {{ limit }} znaków',
                        'maxMessage' => 'Nazwa klasy nie może mieć więcej niż {{ limit }} znaków',
                    ]),
                ],
            ])
            ->add('schoolName', TextType::class, [
                'label' => 'Nazwa szkoły',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Nazwa szkoły jest wymagana',
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Nazwa szkoły musi mieć co najmniej {{ limit }} znaków',
                        'maxMessage' => 'Nazwa szkoły nie może mieć więcej niż {{ limit }} znaków',
                    ]),
                ],
            ])
            ->add('blikPhone', TextType::class, [
                'label' => 'Telefon BLIK',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Telefon BLIK jest wymagany',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[0-9]{9}$/',
                        'message' => 'Telefon BLIK musi składać się z 9 cyfr',
                    ]),
                ],
            ])
            ->add('bankAccount', TextType::class, [
                'label' => 'Numer konta bankowego',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Numer konta bankowego jest wymagany',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[0-9]{26}$/',
                        'message' => 'Numer konta bankowego musi składać się z 26 cyfr',
                    ]),
                ],
            ])
            ->add('email', TextType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Email jest wymagany',
                    ]),
                    new Assert\Email([
                        'message' => 'Podaj prawidłowy adres email',
                    ]),
                ],
            ])
            ->add('appPassword', TextType::class, [
                'label' => 'Hasło aplikacji',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Hasło aplikacji jest wymagane',
                    ]),
                    new Assert\Length([
                        'min' => 8,
                        'minMessage' => 'Hasło aplikacji musi mieć co najmniej {{ limit }} znaków',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Zakończ konfigurację',
            ])
            ->getForm();
    }
}
