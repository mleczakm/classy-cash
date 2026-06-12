<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Command\RecalculateCashState;
use App\Entity\User;
use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\ClassCouncil\ClassExpense;
use App\Entity\Contribution;
use App\Entity\Payment;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\ClassCouncil\StudentRepository;
use App\Repository\ContributionRepository;
use App\Repository\TransferRepository;
use App\Repository\UserRepository;
use App\Application\Service\SettingsDtoMapper;
use App\Form\Type\SettingsType;
use App\Settings\Settings;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/')]
#[IsGranted('ROLE_USER')]
final class TreasurerController extends AbstractController
{
    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly ClassMembershipRepository $memberships,
        private readonly StudentRepository $students,
        private readonly UserRepository $users,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly ContributionRepository $contributions,
        private readonly TransferRepository $transfers,
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $commandBus,
    ) {}

    #[Route('/treasurer/dashboard', name: 'treasurer_dashboard', methods: ['GET'])]
    #[Route('/treasurer', name: 'cc_treasurer_overview', methods: ['GET'])]
    public function dashboard(): Response
    {
        if ($this->users->countUsers() === 0) {
            return $this->redirectToRoute('onboarding');
        }

        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        $this->assertTreasurer($class);

        return $this->render('treasurer/dashboard.html.twig', [
            'classRoom' => $class,
            'appName' => $this->settings->getName(),
        ]);
    }

    #[Route('/treasurer/payments', name: 'treasurer_payments', methods: ['GET'])]
    public function payments(): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        $this->assertTreasurer($class);

        $transfers = $this->transfers->findBy([], [
            'transferredAt' => 'DESC',
        ]);

        $lastSuccessfulImportDate = $this->settings->getLastSuccessfulTransferImportDate();

        return $this->render('treasurer/payments.html.twig', [
            'classRoom' => $class,
            'transfers' => $transfers,
            'lastSuccessfulImportDate' => $lastSuccessfulImportDate,
        ]);
    }

    #[Route('/treasurer/payments/{id}/assign', name: 'treasurer_assign_transfer', methods: ['GET', 'POST'])]
    public function assignTransfer(int $id, Request $request): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        $this->assertTreasurer($class);

        $transfer = $this->transfers->find($id);
        if (! $transfer) {
            throw $this->createNotFoundException('Nie znaleziono przelewu');
        }

        if ($request->isMethod('POST')) {
            $studentPaymentId = $request->request->get('student_payment_id');
            if ($studentPaymentId) {
                try {
                    $studentPayment = $this->studentPayments->find(Ulid::fromString((string) $studentPaymentId));
                    if ($studentPayment) {
                        /** @var User $user */
                        $user = $this->getUser();

                        $payment = $studentPayment->getPayment();
                        if (! $payment) {
                            $amount = Money::of($transfer->amount, 'PLN');
                            $payment = new Payment($user, $amount);
                            $this->em->persist($payment);
                            $studentPayment->setPayment($payment);
                        }

                        $transfer->setPayment($payment);
                        $studentPayment->markPaid();

                        $this->em->flush();

                        $this->commandBus->dispatch(new RecalculateCashState(payment: $payment));

                        $this->addFlash('success', 'Przelew został przypisany pomyślnie');
                        return $this->redirectToRoute('treasurer_payments');
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Wystąpił błąd podczas przypisywania: ' . $e->getMessage());
                }
            }
        }

        $pendingPayments = $this->studentPayments->findBy([
            'classRoom' => $class,
            'status' => [StudentPayment::STATUS_PENDING, StudentPayment::STATUS_PARTIAL],
        ]);

        return $this->render('treasurer/assign_transfer.html.twig', [
            'classRoom' => $class,
            'transfer' => $transfer,
            'pendingPayments' => $pendingPayments,
        ]);
    }

    #[Route('/treasurer/contributions', name: 'treasurer_contributions', methods: ['GET', 'POST'])]
    public function contributions(Request $request): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        $this->assertTreasurer($class);

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $amountStr = trim((string) $request->request->get('amount_pln', ''));
            $dueAtStr = (string) $request->request->get('due_at', '');
            $studentIds = $request->request->all('students');

            if ($title === '' || $amountStr === '') {
                $this->addFlash('error', 'Tytuł i kwota składki są wymagane');
            } else {
                $amount = Money::of($amountStr, 'PLN');
                $dueAt = $dueAtStr !== '' ? new \DateTimeImmutable($dueAtStr) : null;

                $contribution = new Contribution($class, $title, $amount, $dueAt);

                // Assign selected students
                foreach ($studentIds as $studentId) {
                    if (! is_string($studentId) && ! is_int($studentId)) {
                        continue;
                    }
                    try {
                        $student = $this->students->find(Ulid::fromString((string) $studentId));
                        if ($student) {
                            $contribution->addStudent($student);
                        }
                    } catch (\Throwable) {
                        // Invalid ULID, skip
                    }
                }

                $this->contributions->save($contribution);

                // Create student payments for each assigned student
                foreach ($contribution->getStudents() as $student) {
                    $studentPayment = new StudentPayment($student, $title, $amount);
                    if ($dueAt) {
                        $studentPayment->setDueAt($dueAt);
                    }
                    $this->em->persist($studentPayment);
                }
                $this->em->flush();

                $this->addFlash('success', 'Składka została utworzona');
                return $this->redirectToRoute('treasurer_contributions');
            }
        }

        $activeContributions = $this->contributions->findActiveByClass($class);
        $students = $this->students->findBy([
            'classRoom' => $class,
        ], [
            'lastName' => 'ASC',
            'firstName' => 'ASC',
        ]);

        return $this->render('treasurer/contributions.html.twig', [
            'classRoom' => $class,
            'contributions' => $activeContributions,
            'students' => $students,
        ]);
    }

    #[Route('/treasurer/settings', name: 'treasurer_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, SettingsDtoMapper $settingsDtoMapper): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        $this->assertTreasurer($class);

        $dto = $settingsDtoMapper->create();
        $form = $this->createForm(SettingsType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settingsDtoMapper->save($dto);
            $this->addFlash('success', 'Ustawienia zostały zapisane.');

            return $this->redirectToRoute('treasurer_settings');
        }

        return $this->render('treasurer/settings.html.twig', [
            'classRoom' => $class,
            'form' => $form,
        ]);
    }

    #[Route('/treasurer/students', name: 'treasurer_students', methods: ['GET', 'POST'])]
    public function students(Request $request): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        $this->assertTreasurer($class);

        // Handle student merging
        if ($request->isMethod('POST') && $request->request->has('merge_students')) {
            $keepStudentId = $request->request->get('keep_student_id');
            $deleteStudentId = $request->request->get('delete_student_id');

            if ($keepStudentId && $deleteStudentId) {
                try {
                    $keepUlid = is_string($keepStudentId) || is_int($keepStudentId) ? (string) $keepStudentId : '';
                    $deleteUlid = is_string($deleteStudentId) || is_int(
                        $deleteStudentId
                    ) ? (string) $deleteStudentId : '';

                    $keepStudent = $this->students->find(Ulid::fromString($keepUlid));
                    $deleteStudent = $this->students->find(Ulid::fromString($deleteUlid));

                    if ($keepStudent && $deleteStudent) {
                        $this->mergeStudents($keepStudent, $deleteStudent);
                        $this->addFlash('success', 'Uczniowie zostali połączeni');
                        return $this->redirectToRoute('treasurer_students');
                    }
                    $this->addFlash('error', 'Nie znaleziono uczniów do połączenia');
                } catch (\Throwable) {
                    $this->addFlash('error', 'Nieprawidłowe identyfikatory uczniów');
                }
                return $this->redirectToRoute('treasurer_students');
            }
        }

        $students = $this->students->findBy([
            'classRoom' => $class,
        ], [
            'lastName' => 'ASC',
            'firstName' => 'ASC',
        ]);

        return $this->render('treasurer/students.html.twig', [
            'classRoom' => $class,
            'students' => $students,
        ]);
    }

    #[Route('/treasurer/manual-transactions', name: 'treasurer_manual_transactions', methods: ['GET', 'POST'])]
    public function manualTransactions(Request $request): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            return $this->redirectToRoute('onboarding');
        }

        $this->assertTreasurer($class);

        if ($request->isMethod('POST')) {
            $type = $request->request->get('transaction_type');
            $amountStr = trim((string) $request->request->get('amount_pln', ''));

            if ($amountStr === '') {
                $this->addFlash('error', 'Kwota jest wymagana');
                return $this->redirectToRoute('treasurer_manual_transactions');
            }
            $amount = Money::of($amountStr, 'PLN');

            if ($type === 'income') {
                $description = (string) $request->request->get('income_description', '');
                $studentPaymentId = $request->request->get('student_payment_id');

                if ($studentPaymentId) {
                    try {
                        $spId = is_string($studentPaymentId) || is_int(
                            $studentPaymentId
                        ) ? (string) $studentPaymentId : '';
                        $studentPayment = $this->studentPayments->find(Ulid::fromString($spId));
                        if ($studentPayment) {
                            /** @var User $user */
                            $user = $this->getUser();
                            // Create payment and link to student payment
                            $payment = new Payment($user, $amount);
                            $this->em->persist($payment);
                            $studentPayment->setPayment($payment);
                            $studentPayment->markPaid();
                            $this->em->flush();

                            $this->commandBus->dispatch(new RecalculateCashState(payment: $payment));

                            $this->addFlash('success', 'Wpłata została zaksięgowana');
                            return $this->redirectToRoute('treasurer_manual_transactions');
                        }
                    } catch (\Throwable) {
                        $this->addFlash('error', 'Nieprawidłowa płatność ucznia');
                        return $this->redirectToRoute('treasurer_manual_transactions');
                    }
                } else {
                    /** @var User $user */
                    $user = $this->getUser();
                    // General income - create a standalone payment record
                    $payment = new Payment($user, $amount);
                    $this->em->persist($payment);
                    $this->em->flush();

                    $this->commandBus->dispatch(new RecalculateCashState(payment: $payment));

                    $this->addFlash('success', 'Przychód został zaksięgowany');
                    return $this->redirectToRoute('treasurer_manual_transactions');
                }
            } elseif ($type === 'expense') {
                $description = (string) $request->request->get('expense_description', '');
                if ($description === '') {
                    $this->addFlash('error', 'Opis wydatku jest wymagany');
                    return $this->redirectToRoute('treasurer_manual_transactions');
                }
                // Create expense record
                $expense = new ClassExpense($class, $description, $amount);
                $this->em->persist($expense);
                $this->em->flush();

                $this->commandBus->dispatch(new RecalculateCashState(expense: $expense));

                $this->addFlash('success', 'Wydatek został zaksięgowany');
                return $this->redirectToRoute('treasurer_manual_transactions');

            }

        }

        $students = $this->students->findBy([
            'classRoom' => $class,
        ], [
            'lastName' => 'ASC',
            'firstName' => 'ASC',
        ]);
        $studentPayments = $this->studentPayments->findByClass($class);

        return $this->render('treasurer/manual_transactions.html.twig', [
            'classRoom' => $class,
            'students' => $students,
            'studentPayments' => $studentPayments,
        ]);
    }

    private function mergeStudents(Student $keepStudent, Student $deleteStudent): void
    {
        try {
            // Reassign payments to kept student
            $payments = $this->studentPayments->findBy([
                'student' => $deleteStudent,
            ]);
            foreach ($payments as $payment) {
                $payment->setStudent($keepStudent);
            }

            // Reassign parents to kept student
            foreach ($deleteStudent->getParents()->toArray() as $parent) {
                $keepStudent->addParent($parent);
                $deleteStudent->removeParent($parent);
            }

            // Reassign contributions to kept student
            foreach ($deleteStudent->getContributions()->toArray() as $contribution) {
                if (! $contribution->getStudents()->contains($keepStudent)) {
                    $contribution->addStudent($keepStudent);
                }
                $contribution->removeStudent($deleteStudent);
            }

            // Soft delete the duplicate student
            $deleteStudent->setDeletedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function assertTreasurer(ClassRoom $class): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $this->memberships->findOneBy([
            'user' => $user,
            'classRoom' => $class,
        ]);

        if (! $membership || $membership->getRole() !== ClassRole::TREASURER) {
            throw $this->createAccessDeniedException();
        }
    }
}
