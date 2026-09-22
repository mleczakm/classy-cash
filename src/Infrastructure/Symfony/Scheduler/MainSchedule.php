<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Scheduler;

use App\Application\Command\CheckExpiredPayments;
use App\Application\Command\SampleResourceUsage;
use App\Application\Command\TriggerMatchPaymentForTransferForPastTransfers;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('main')]
final readonly class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::every('5 minutes', new CheckExpiredPayments(expirationMinutes: 24 * 60)),
                // Disabled 2026-09-22: no fees currently need importing, and every-30s retries on
                // a wedged IMAP connection are what took the site down for ~20h (see
                // AliorNotificationMailProvider - fgets() on the IMAP stream can block forever,
                // Swoole force-kills the single HTTP worker, and the scheduler never recovers a
                // coroutine context afterwards). Before re-enabling: fix the missing read timeout
                // on the IMAP stream (stream_set_timeout does not reliably apply to TLS reads) and
                // consider worker_count > 1 so one wedged coroutine cannot take down all requests.
                // RecurringMessage::every(30, new ImportTransfersFromMail()),
                RecurringMessage::every(60, new SampleResourceUsage()),
                RecurringMessage::every(60, new TriggerMatchPaymentForTransferForPastTransfers()),
            );
    }
}
