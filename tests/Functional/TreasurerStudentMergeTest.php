<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClassCouncil\ClassRole;
use PHPUnit\Framework\Attributes\Group;
use Brick\Money\Money;

#[Group('functional')]
class TreasurerStudentMergeTest extends FunctionalTestCase
{
    public function testMergeStudentsReassignsPaymentsCorrectly(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $keepStudent = $this->createStudent($classRoom, 'Jan', 'Kowalski');
        $deleteStudent = $this->createStudent($classRoom, 'Jan', 'Kowalski');

        // Create payments for the student to be deleted
        $payment1 = $this->createStudentPayment($deleteStudent, 'Wycieczka', Money::of(35, 'PLN'));
        $payment2 = $this->createStudentPayment($deleteStudent, 'Kino', Money::of(25, 'PLN'));

        // Log in as treasurer
        $user = $this->createUser('treasurer@example.com', 'password');
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);
        $this->client->loginUser($user);

        // Perform merge
        $this->client->request('POST', '/treasurer/students', [
            'merge_students' => '1',
            'keep_student_id' => $keepStudent->getId()
                ->toRfc4122(),
            'delete_student_id' => $deleteStudent->getId()
                ->toRfc4122(),
        ]);

        $this->assertResponseRedirects('/treasurer/students');

        // Verify payments reassigned to kept student
        $this->getEntityManager()
            ->refresh($payment1);
        $this->getEntityManager()
            ->refresh($payment2);

        $this->assertEquals($keepStudent->getId(), $payment1->getStudent()->getId());
        $this->assertEquals($keepStudent->getId(), $payment2->getStudent()->getId());

        // Verify soft delete
        $this->getEntityManager()
            ->refresh($deleteStudent);
        $this->assertNotNull($deleteStudent->getDeletedAt());
    }

    public function testMergeStudentsReassignsParentsCorrectly(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $keepStudent = $this->createStudent($classRoom, 'Jan', 'Kowalski');
        $deleteStudent = $this->createStudent($classRoom, 'Anna', 'Nowak');

        // Add a parent to the student to be deleted
        $parent = $this->createUser('parent@example.com', 'password');
        $deleteStudent->addParent($parent);
        $this->getEntityManager()
            ->persist($deleteStudent);
        $this->getEntityManager()
            ->flush();

        // Log in
        $user = $this->createUser('treasurer2@example.com', 'password');
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);
        $this->client->loginUser($user);

        // Perform merge
        $this->client->request('POST', '/treasurer/students', [
            'merge_students' => '1',
            'keep_student_id' => $keepStudent->getId()
                ->toRfc4122(),
            'delete_student_id' => $deleteStudent->getId()
                ->toRfc4122(),
        ]);

        $this->assertResponseRedirects('/treasurer/students');

        // Verify parent reassigned to kept student
        $this->getEntityManager()
            ->refresh($keepStudent);
        $this->assertTrue($keepStudent->getParents()->contains($parent));

        // Verify parent removed from deleted student
        $this->getEntityManager()
            ->refresh($deleteStudent);
        $this->assertFalse($deleteStudent->getParents()->contains($parent));
    }

    public function testMergeStudentsHandlesInvalidIds(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('treasurer3@example.com', 'password');
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);
        $this->client->loginUser($user);

        $this->client->request('POST', '/treasurer/students', [
            'merge_students' => '1',
            'keep_student_id' => 'invalid-id',
            'delete_student_id' => 'another-invalid-id',
        ]);

        $this->assertResponseRedirects('/treasurer/students');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger'); // Error message should be shown
    }

    public function testMergeStudentsRequiresAuthentication(): void
    {
        // Don't log in
        $this->client->request('POST', '/treasurer/students', [
            'merge_students' => '1',
            'keep_student_id' => 'some-id',
            'delete_student_id' => 'some-other-id',
        ]);

        $this->assertResponseRedirects('/login');
    }
}
