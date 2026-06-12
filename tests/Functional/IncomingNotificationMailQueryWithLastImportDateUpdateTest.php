<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use App\Application\CommandHandler\IncomingNotificationMailQueryWithLastImportDateUpdate;
use App\Settings\Settings;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use DirectoryTree\ImapEngine\Testing\FakeMessageQuery;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class IncomingNotificationMailQueryWithLastImportDateUpdateTest extends FunctionalTestCase
{
    public function testUpdatesLastSuccessfulImportDateWhenMessagesFound(): void
    {
        /** @var Settings $settings */
        $settings = $this->getService(Settings::class);
        $innerQuery = new FakeQueryWithMessages();

        $decorator = new IncomingNotificationMailQueryWithLastImportDateUpdate($innerQuery, $settings);

        iterator_to_array($decorator());

        $lastImportDate = $settings->getLastSuccessfulTransferImportDate();
        $this->assertNotNull($lastImportDate);
        $this->assertLessThanOrEqual(new \DateTimeImmutable(), $lastImportDate);
    }

    public function testUpdatesLastSuccessfulImportDateWhenNoMessagesFound(): void
    {
        /** @var Settings $settings */
        $settings = $this->getService(Settings::class);
        $innerQuery = new FakeQueryWithoutMessages();

        $decorator = new IncomingNotificationMailQueryWithLastImportDateUpdate($innerQuery, $settings);

        iterator_to_array($decorator());

        $lastImportDate = $settings->getLastSuccessfulTransferImportDate();
        $this->assertNotNull($lastImportDate);
        $this->assertLessThanOrEqual(new \DateTimeImmutable(), $lastImportDate);
    }

    public function testDoesNotUpdateLastSuccessfulImportDateWhenQueryFails(): void
    {
        /** @var Settings $settings */
        $settings = $this->getService(Settings::class);
        $innerQuery = new FakeQueryThatThrows();

        $decorator = new IncomingNotificationMailQueryWithLastImportDateUpdate($innerQuery, $settings);

        $this->expectException(\RuntimeException::class);

        try {
            iterator_to_array($decorator());
        } catch (\RuntimeException $e) {
            // The setting should not have been updated
            $lastImportDate = $settings->getLastSuccessfulTransferImportDate();
            $this->assertNull($lastImportDate);
            throw $e;
        }
    }
}

final class FakeQueryWithMessages implements IncomingNotificationMailQuery
{
    /**
     * @return iterable<FakeMessageQuery>
     */
    public function __invoke(): iterable
    {
        $folder = new FakeFolder('inbox');
        $folder->addMessage(new FakeMessage(uid: 1, flags: [], contents: 'test'));
        yield new FakeMessageQuery($folder);
    }
}

final class FakeQueryWithoutMessages implements IncomingNotificationMailQuery
{
    public function __invoke(): iterable
    {
        return [];
    }
}

final class FakeQueryThatThrows implements IncomingNotificationMailQuery
{
    public function __invoke(): iterable
    {
        throw new \RuntimeException('Query failed');
    }
}
