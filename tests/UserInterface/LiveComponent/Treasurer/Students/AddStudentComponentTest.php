<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\LiveComponent\Treasurer\Students;

use PHPUnit\Framework\Attributes\Group;
use App\Tests\Functional\FunctionalTestCase;
use App\UserInterface\LiveComponent\Treasurer\Students\AddStudentComponent;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class AddStudentComponentTest extends FunctionalTestCase
{
    use InteractsWithLiveComponents;

    public function testComponentDisplaysCorrectly(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        $this->assertSame('4B', $component->classCode);
    }

    public function testComponentValidatesRequiredFields(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        // Submit empty form
        $component->addStudent();

        $this->assertHasErrors(['firstName', 'lastName']);
        $this->assertStringContainsString('Imię i nazwisko są wymagane', $component->getErrors()['firstName'] ?? '');
    }

    public function testComponentCreatesStudentSuccessfully(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $parent1 = $this->createUser('parent1@example.com', 'password');
        $parent2 = $this->createUser('parent2@example.com', 'password');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        // Set form data
        $component->firstName = 'Jan';
        $component->lastName = 'Kowalski';
        $component->selectedParents = [$parent1->getId(), $parent2->getId()];

        $component->call('addStudent');

        $this->assertEmpty($component->getErrors());

        // Verify student was created
        $this->em->clear();
        $student = $this->students->findOneBy([
            'firstName' => 'Jan',
            'lastName' => 'Kowalski',
        ]);

        $this->assertNotNull($student);
        $this->assertEquals('Jan', $student->getFirstName());
        $this->assertEquals('Kowalski', $student->getLastName());

        // Verify parents were linked
        $this->em->refresh($student);
        $parents = $student->getParents();
        $this->assertCount(2, $parents);
        $this->assertContains($parent1, $parents);
        $this->assertContains($parent2, $parents);
    }

    public function testComponentEmitsStudentAddedEvent(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        $component->firstName = 'Jan';
        $component->lastName = 'Kowalski';

        $component->call('addStudent');

        $this->assertEventEmitted('studentAdded');
    }

    public function testComponentSearchesParents(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $parent1 = $this->createUser('parent1@example.com', 'password');
        $parent2 = $this->createUser('parent2@example.com', 'password');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        // Test search functionality
        $results = $component->call('searchParents', ['parent']);
        $this->assertCount(2, $results);
        $this->assertContains($parent1, $results);
        $this->assertContains($parent2, $results);

        // Test search with insufficient characters
        $this->assertCount(0, $component->call('searchParents', ['a']));
    }

    public function testComponentManagesParentSelection(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $parent1 = $this->createUser('parent1@example.com', 'password');
        $parent2 = $this->createUser('parent2@example.com', 'password');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        // Add first parent
        $component->call('addParent', [$parent1->getId()]);
        $this->assertEquals([$parent1->getId()], $component->selectedParents);

        // Add second parent
        $component->call('addParent', [$parent2->getId()]);
        $this->assertEquals([$parent1->getId(), $parent2->getId()], $component->selectedParents);

        // Remove first parent
        $component->call('removeParent', [$parent1->getId()]);
        $this->assertEquals([$parent2->getId()], $component->selectedParents);

        // Add back first parent
        $component->call('addParent', [$parent1->getId()]);
        $this->assertEquals([$parent1->getId(), $parent2->getId()], $component->selectedParents);
    }

    public function testComponentResetsForm(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $parent = $this->createUser('parent@example.com', 'password');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        // Set form data
        $component->firstName = 'Jan';
        $component->lastName = 'Kowalski';
        $component->selectedParents = [$parent->getId()];

        // Reset form
        $component->call('resetForm');

        $this->assertNull($component->firstName);
        $this->assertNull($component->lastName);
        $this->assertEmpty($component->selectedParents);
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        $component = $this->createLiveComponent(AddStudentComponent::class);

        $this->assertNull($component->classCode);

        // Try to add student without class room
        $component->firstName = 'Jan';
        $component->lastName = 'Kowalski';

        $component->call('addStudent');

        $this->assertHasErrors(['firstName', 'lastName']);
        $this->assertStringContainsString('Nie znaleziono klasy', $component->getErrors()['firstName'] ?? '');
    }

    public function testGetPotentialParentsExcludesAlreadySelected(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $parent1 = $this->createUser('parent1@example.com', 'password');
        $parent2 = $this->createUser('parent2@example.com', 'password');

        $component = $this->createLiveComponent(AddStudentComponent::class);

        // Select one parent
        $component->selectedParents = [$parent1->getId()];

        $potentialParents = $component->call('getPotentialParents');
        $this->assertCount(1, $potentialParents);
        $this->assertNotContains($parent1, $potentialParents);
        $this->assertContains($parent2, $potentialParents);
    }
}
