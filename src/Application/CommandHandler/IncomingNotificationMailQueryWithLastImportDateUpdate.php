<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Settings\Settings;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use Symfony\Component\Clock\Clock;

final readonly class IncomingNotificationMailQueryWithLastImportDateUpdate implements IncomingNotificationMailQuery
{
    public const string LAST_SUCCESSFUL_IMPORT_DATE_KEY = 'last_successful_transfer_import_date';

    public function __construct(
        private IncomingNotificationMailQuery $inner,
        private Settings $settings,
    ) {}

    /**
     * @return iterable<MessageQueryInterface>
     */
    public function __invoke(): iterable
    {
        $messages = ($this->inner)();

        try {
            foreach ($messages as $message) {
                yield $message;
            }

            // Update last successful import date when query completes successfully
            $this->settings->set(
                self::LAST_SUCCESSFUL_IMPORT_DATE_KEY,
                Clock::get()->now()->format(\DateTimeInterface::ATOM)
            );
        } catch (\Throwable $e) {
            // If an error occurs, re-throw it without updating the setting
            throw $e;
        }
    }
}
