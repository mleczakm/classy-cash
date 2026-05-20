<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Entity\Transfer;
use PHPUnit\Framework\Attributes\Group;
use App\Application\Command\SaveTransfer;
use App\Tests\Assembler\TransferAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('functional')]
class SaveTransferHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    public function testExpectedAmountsAreGettingSaved(): void
    {
        $transfer = TransferAssembler::new()->withAmount('340')->assemble();
        self::bootKernel();


        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        $messageBus->dispatch(new SaveTransfer($transfer));
        $this->transport('async')
            ->process();

        $savedTransfer = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Transfer::class)
            ->findOneBy([
                'title' => $transfer->title,
                'amount' => $transfer->amount,
            ]);

        self::assertNotNull($savedTransfer);
        self::assertIsNumeric($savedTransfer->getId());
    }
}
