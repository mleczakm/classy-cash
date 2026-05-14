<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\User;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use Brick\Money\Money;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\ClassCouncil\ClassExpense;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class FunctionalTestCase extends WebTestCase
{
    use FunctionalTestSettingsTrait;

    protected ?EntityManagerInterface $em = null;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $em = static::getContainer()
            ->get(EntityManagerInterface::class);
        $this->em = $em;
    }

    protected function getService(string $class): object
    {
        return static::getContainer()->get($class);
    }

    protected function createUser(string $email, string $password): User
    {
        $user = UserAssembler::new()
            ->withEmail($email)
            ->assemble();

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $this->getService(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->getEntityManager()
            ->persist($user);
        $this->getEntityManager()
            ->flush();

        return $user;
    }

    protected function createClassRoom(string $name): ClassRoom
    {
        $classRoom = new ClassRoom($name);

        $this->getEntityManager()
            ->persist($classRoom);
        $this->getEntityManager()
            ->flush();

        return $classRoom;
    }

    protected function createMembership(User $user, ClassRoom $classRoom, ClassRole $role): ClassMembership
    {
        $membership = new ClassMembership($user, $classRoom, $role);

        $this->getEntityManager()
            ->persist($membership);
        $this->getEntityManager()
            ->flush();

        return $membership;
    }

    protected function createStudent(ClassRoom $classRoom, string $firstName, string $lastName): Student
    {
        $student = new Student($classRoom, $firstName, $lastName);

        $this->getEntityManager()
            ->persist($student);
        $this->getEntityManager()
            ->flush();

        return $student;
    }

    protected function createTestStudent(ClassRoom $classRoom): Student
    {
        return $this->createStudent($classRoom, 'Test', 'Student');
    }

    protected function createStudentPayment(Student $student, string $label, Money $amount): StudentPayment
    {
        $payment = new StudentPayment($student, $label, $amount);

        $this->getEntityManager()
            ->persist($payment);
        $this->getEntityManager()
            ->flush();

        return $payment;
    }

    protected function createClassExpense(ClassRoom $classRoom, string $label, Money $amount): ClassExpense
    {
        $expense = new ClassExpense($classRoom, $label, $amount);

        $this->getEntityManager()
            ->persist($expense);
        $this->getEntityManager()
            ->flush();

        return $expense;
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        if (! $this->em instanceof EntityManagerInterface) {
            throw new \RuntimeException('EntityManager not initialized');
        }
        return $this->em;
    }
}
