<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\TransferPaymentMatchHint;
use App\Application\Service\TransferPaymentMatcher;
use App\Entity\Payment;
use App\Entity\Transfer;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class AssignPaymentModalComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp]
    public Transfer $transfer;

    #[LiveProp(writable: true)]
    public string $paymentSearch = '';

    #[LiveProp(writable: true)]
    public ?string $selectedPaymentId = null;

    /**
     * @var array{payments: Payment[], hints: array<string, TransferPaymentMatchHint>}|null
     */
    private ?array $paymentEnrichment = null;

    private ?string $cachedPaymentSearch = null;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly TransferPaymentMatcher $transferPaymentMatcher,
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $paymentStateMachine,
        private readonly StudentPaymentRepository $studentPayments,
        #[Autowire(service: 'state_machine.student_payment')]
        private readonly WorkflowInterface $studentPaymentStateMachine,
    ) {}

    /**
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->getPaymentEnrichment()['payments'];
    }

    /**
     * @return array<string, TransferPaymentMatchHint>
     */
    public function getMatchHints(): array
    {
        return $this->getPaymentEnrichment()['hints'];
    }

    public function getTopHistoricalHint(): ?TransferPaymentMatchHint
    {
        $hints = $this->getMatchHints();
        if ($hints === []) {
            return null;
        }

        usort(
            $hints,
            static fn(TransferPaymentMatchHint $left, TransferPaymentMatchHint $right): int => $right->score <=> $left->score
        );

        return $hints[0];
    }

    /**
     * @return array{payments: Payment[], hints: array<string, TransferPaymentMatchHint>}
     */
    private function getPaymentEnrichment(): array
    {
        if ($this->paymentEnrichment === null || $this->cachedPaymentSearch !== $this->paymentSearch) {
            $payments = $this->paymentRepository->findAssignableWithSearch($this->paymentSearch);
            $this->paymentEnrichment = $this->transferPaymentMatcher->enrichAssignablePayments(
                $this->transfer,
                $payments
            );
            $this->cachedPaymentSearch = $this->paymentSearch;
        }

        return $this->paymentEnrichment;
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;
    }

    #[LiveAction]
    public function selectPayment(#[LiveArg] string $paymentId): void
    {
        $this->selectedPaymentId = $paymentId;
    }

    #[LiveAction]
    public function confirmAssignment(): void
    {
        if (! $this->selectedPaymentId) {
            return;
        }

        $payment = $this->paymentRepository->find(Ulid::fromString($this->selectedPaymentId));
        if (! $payment) {
            return;
        }

        $this->transfer->setPayment($payment);
        if ($this->paymentStateMachine->can($payment, 'pay')) {
            $this->paymentStateMachine->apply($payment, 'pay');
        }

        // If there are any Class Council student payments linked to this Payment, use workflow to settle them
        foreach ($this->studentPayments->findByPayment($payment) as $sp) {
            if ($this->studentPaymentStateMachine->can($sp, 'settle')) {
                $this->studentPaymentStateMachine->apply($sp, 'settle');
                $sp->setPaidAt(new \DateTimeImmutable());
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Przelew został pomyślnie przypisany do płatności.');

        $this->closeModal();

        $this->redirectToRoute('treasurer_payments');
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
        $this->paymentSearch = '';
        $this->selectedPaymentId = null;
        $this->paymentEnrichment = null;
        $this->cachedPaymentSearch = null;
    }
}
