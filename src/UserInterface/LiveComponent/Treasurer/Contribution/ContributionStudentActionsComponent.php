<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Contribution;

use App\Entity\Contribution;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\User;
use App\Entity\ClassCouncil\ClassRole;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('ContributionStudentActions', template: 'components/treasurer/contribution/student_actions.html.twig')]
class ContributionStudentActionsComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?StudentPayment $studentPayment = null;

    #[LiveProp]
    public Contribution $contribution;

    #[LiveProp]
    public Student $student;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClassMembershipRepository $memberships,
    ) {}

    #[LiveAction]
    public function createPayment(): void
    {
        $this->assertTreasurer();

        if (!$this->studentPayment || $this->studentPayment->getPayment()) {
            return;
        }

        /** @var User $user */
        $user = $this->getUser();
        $payment = new Payment($user, $this->studentPayment->getAmount());
        new PaymentCode($payment);
        $this->entityManager->persist($payment);

        $this->studentPayment->setPayment($payment);
        $this->entityManager->flush();

        $this->addFlash('success', 'Płatność została utworzona');
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->assertTreasurer();

        $this->contribution->removeStudent($this->student);

        if ($this->studentPayment) {
            if ($this->studentPayment->getStatus() === StudentPayment::STATUS_PAID) {
                $this->addFlash('error', 'Nie można anulować opłaconej składki');
                return;
            }

            if ($payment = $this->studentPayment->getPayment()) {
                $payment->getStudentPayments()->removeElement($this->studentPayment);
                if ($payment->getStudentPayments()->isEmpty()) {
                    $this->entityManager->remove($payment);
                }
            }
            $this->entityManager->remove($this->studentPayment);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Obowiązek zapłaty został anulowany');
    }

    private function assertTreasurer(): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $class = $this->contribution->getClassRoom();

        $membership = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);

        if (! $membership || $membership->getRole() !== ClassRole::TREASURER) {
            throw $this->createAccessDeniedException();
        }
    }
}
