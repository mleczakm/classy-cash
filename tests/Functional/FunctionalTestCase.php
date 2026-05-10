<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
        $this->em = static::getContainer()
            ->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->em) {
            $this->em->close();
        }
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

        $hasher = $this->getService(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createClassRoom(string $name): ClassRoom
    {
        $classRoom = new ClassRoom($name);

        $this->em->persist($classRoom);
        $this->em->flush();

        return $classRoom;
    }

    protected function createStudent(ClassRoom $classRoom, string $firstName, string $lastName): Student
    {
        $student = new Student($classRoom, $firstName, $lastName);

        $this->em->persist($student);
        $this->em->flush();

        return $student;
    }

    protected function createTestStudent(ClassRoom $classRoom): Student
    {
        return $this->createStudent($classRoom, 'Test', 'Student');
    }

    protected function createStudentPayment(Student $student, string $label, Money $amount): StudentPayment
    {
        $payment = new StudentPayment($student, $label, $amount);

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    protected function createClassExpense(ClassRoom $classRoom, string $label, Money $amount): ClassExpense
    {
        $expense = new ClassExpense($classRoom, $label, $amount);

        $this->em->persist($expense);
        $this->em->flush();

        return $expense;
    }
}
