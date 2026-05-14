<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\User;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\UserRepository;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly ClassMembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly StudentPaymentRepository $studentPayments,
    ) {}

    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        if ($this->users->countUsers() === 0) {
            return $this->redirectToRoute('onboarding');
        }

        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        /** @var User $user */
        $user = $this->getUser();
        $membership = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);

        if ($membership && $membership->getRole() === ClassRole::TREASURER) {
            return $this->redirectToRoute('treasurer_dashboard');
        }

        return $this->redirectToRoute('parent_dashboard');
    }

    #[Route('/parent', name: 'parent_dashboard')]
    public function parentDashboard(): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        /** @var User $user */
        $user = $this->getUser();
        
        // Parent View Data
        $students = $user->getStudents();
        $unpaidPayments = [];
        $totalUnpaid = Money::of(0, 'PLN');

        if (!$students->isEmpty()) {
            $allPayments = $this->studentPayments->findForStudents($students->toArray());
            foreach ($allPayments as $payment) {
                if ($payment->getStatus() !== StudentPayment::STATUS_PAID) {
                    $unpaidPayments[] = $payment;
                    $totalUnpaid = $totalUnpaid->plus($payment->getAmount());
                }
            }
        }

        $membership = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);
        $isTreasurer = $membership && $membership->getRole() === ClassRole::TREASURER;

        return $this->render('parent/dashboard.html.twig', [
            'classRoom' => $class,
            'unpaidPayments' => $unpaidPayments,
            'totalUnpaid' => $totalUnpaid,
            'isTreasurer' => $isTreasurer,
        ]);
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return $this->render('profile.html.twig');
    }
}
