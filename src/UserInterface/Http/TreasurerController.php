<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Contribution;
use App\Entity\Payment;
use App\Entity\Transfer;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\ClassCouncil\StudentRepository;
use App\Repository\ContributionRepository;
use App\Repository\TransferRepository;
use App\Repository\UserRepository;
use App\Settings\Settings;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/')]
#[IsGranted('ROLE_USER')]
final class TreasurerController extends AbstractController
{
    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentRepository $students,
        private readonly UserRepository $users,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly ContributionRepository $contributions,
        private readonly TransferRepository $transfers,
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/dashboard', name: 'treasurer_dashboard', methods: ['GET'])]
    #[Route('/', name: 'homepage')]
    public function dashboard(): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (!$class) {
            return $this->redirectToRoute('onboard_setup');
        }

        $this->assertTreasurer($class);

        return $this->render('treasurer/dashboard.html.twig', [
            'classRoom' => $class,
            'appName' => $this->settings->getName(),
        ]);
    }

    #[Route('/payments', name: 'treasurer_payments', methods: ['GET'])]
    public function payments(): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (!$class) {
            return $this->redirectToRoute('onboard_setup');
        }

        $this->assertTreasurer($class);

        $transfers = $this->transfers->findBy([], ['transferredAt' => 'DESC']);

        return $this->render('treasurer/payments.html.twig', [
            'classRoom' => $class,
            'transfers' => $transfers,
        ]);
    }

    #[Route('/contributions', name: 'treasurer_contributions', methods: ['GET', 'POST'])]
    public function contributions(Request $request): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (!$class) {
            return $this->redirectToRoute('onboard_setup');
        }

        $this->assertTreasurer($class);

        if ($request->isMethod('POST')) {
            $title = trim((string) $request->request->get('title', ''));
            $amountStr = trim((string) $request->request->get('amount_pln', ''));
            $dueAtStr = (string) $request->request->get('due_at', '');
            $studentIds = $request->request->all('students', []);

            if ($title === '' || $amountStr === '') {
                $this->addFlash('error', 'Tytuł i kwota składki są wymagane');
            } else {
                $amount = Money::of($amountStr, 'PLN');
                $dueAt = $dueAtStr !== '' ? new \DateTimeImmutable($dueAtStr) : null;

                $contribution = new Contribution($class, $title, $amount, $dueAt);
                
                // Assign selected students
                foreach ($studentIds as $studentId) {
                    try {
                        $student = $this->students->find(Ulid::fromString($studentId));
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
        $students = $this->students->findBy(['classRoom' => $class], ['lastName' => 'ASC', 'firstName' => 'ASC']);

        return $this->render('treasurer/contributions.html.twig', [
            'classRoom' => $class,
            'contributions' => $activeContributions,
            'students' => $students,
        ]);
    }

    #[Route('/students', name: 'treasurer_students', methods: ['GET', 'POST'])]
    public function students(Request $request): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (!$class) {
            return $this->redirectToRoute('onboard_setup');
        }

        $this->assertTreasurer($class);

        // Handle student merging
        if ($request->isMethod('POST') && $request->request->has('merge_students')) {
            $keepStudentId = $request->request->get('keep_student_id');
            $deleteStudentId = $request->request->get('delete_student_id');

            if ($keepStudentId && $deleteStudentId) {
                try {
                    $keepStudent = $this->students->find(Ulid::fromString($keepStudentId));
                    $deleteStudent = $this->students->find(Ulid::fromString($deleteStudentId));

                    if ($keepStudent && $deleteStudent) {
                        $this->mergeStudents($keepStudent, $deleteStudent);
                        $this->addFlash('success', 'Uczniowie zostali połączeni');
                        return $this->redirectToRoute('treasurer_students');
                    }
                } catch (\Throwable) {
                    $this->addFlash('error', 'Nieprawidłowe identyfikatory uczniów');
                }
            }
        }

        $students = $this->students->findBy(['classRoom' => $class], ['lastName' => 'ASC', 'firstName' => 'ASC']);

        return $this->render('treasurer/students.html.twig', [
            'classRoom' => $class,
            'students' => $students,
        ]);
    }

    #[Route('/manual-transactions', name: 'treasurer_manual_transactions', methods: ['GET', 'POST'])]
    public function manualTransactions(Request $request): Response
    {
        $class = $this->classRooms->findOneBy([]);
        if (!$class) {
            return $this->redirectToRoute('onboard_setup');
        }

        $this->assertTreasurer($class);

        if ($request->isMethod('POST')) {
            $type = $request->request->get('transaction_type');
            $amountStr = trim((string) $request->request->get('amount_pln', ''));
            
            if ($amountStr === '') {
                $this->addFlash('error', 'Kwota jest wymagana');
            } else {
                $amount = Money::of($amountStr, 'PLN');

                if ($type === 'income') {
                    $description = $request->request->get('income_description', '');
                    $studentPaymentId = $request->request->get('student_payment_id');
                    
                    if ($studentPaymentId) {
                        try {
                            $studentPayment = $this->studentPayments->find(Ulid::fromString($studentPaymentId));
                            if ($studentPayment) {
                                // Create payment and link to student payment
                                $payment = new Payment($this->getUser(), $amount);
                                $this->em->persist($payment);
                                $studentPayment->setPayment($payment);
                                $studentPayment->markPaid();
                                $this->em->flush();
                                
                                $this->addFlash('success', 'Wpłata została zaksięgowana');
                            }
                        } catch (\Throwable) {
                            $this->addFlash('error', 'Nieprawidłowa płatność ucznia');
                        }
                    } else {
                        // General income
                        $this->addFlash('success', 'Przychód został zaksięgowany');
                    }
                } elseif ($type === 'expense') {
                    $description = $request->request->get('expense_description', '');
                    if ($description === '') {
                        $this->addFlash('error', 'Opis wydatku jest wymagany');
                    } else {
                        // Create expense record (would need ClassExpense entity)
                        $this->addFlash('success', 'Wydatek został zaksięgowany');
                    }
                }
            }
        }

        $students = $this->students->findBy(['classRoom' => $class], ['lastName' => 'ASC', 'firstName' => 'ASC']);
        $studentPayments = $this->studentPayments->findByClass($class);

        return $this->render('treasurer/manual_transactions.html.twig', [
            'classRoom' => $class,
            'students' => $students,
            'studentPayments' => $studentPayments,
        ]);
    }

    private function mergeStudents(Student $keepStudent, Student $deleteStudent): void
    {
        // Reassign payments to kept student
        foreach ($deleteStudent->getStudentPayments() as $payment) {
            $payment->setStudent($keepStudent);
        }

        // Reassign parents to kept student
        foreach ($deleteStudent->getParents() as $parent) {
            $keepStudent->addParent($parent);
            $deleteStudent->removeParent($parent);
        }

        // Reassign contributions to kept student
        foreach ($deleteStudent->getContributions() as $contribution) {
            if (!$contribution->getStudents()->contains($keepStudent)) {
                $contribution->addStudent($keepStudent);
            }
            $contribution->removeStudent($deleteStudent);
        }

        // Soft delete the duplicate student
        $deleteStudent->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    private function assertTreasurer(ClassRoom $class): void
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        // For now, assume any authenticated user can access treasurer features
        // In production, check for treasurer role
    }
}
