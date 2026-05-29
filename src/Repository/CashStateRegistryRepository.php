<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CashStateRegistry;
use App\Entity\ClassCouncil\ClassExpense;
use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashStateRegistry>
 */
class CashStateRegistryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashStateRegistry::class);
    }

    /**
     * @return CashStateRegistry[]
     */
    public function findByPayment(Payment $payment): array
    {
        return $this->findBy([
            'payment' => $payment,
        ]);
    }

    /**
     * @return CashStateRegistry[]
     */
    public function findByExpense(ClassExpense $expense): array
    {
        return $this->findBy([
            'expense' => $expense,
        ]);
    }

    /**
     * Find all registry entries after a given date
     *
     * @return CashStateRegistry[]
     */
    public function findAfterDate(\DateTimeImmutable $date): array
    {
        /** @var CashStateRegistry[] */
        return $this->createQueryBuilder('csr')
            ->where('csr.transactionDate > :date')
            ->setParameter('date', $date)
            ->orderBy('csr.transactionDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the most recent registry entry before a given date
     */
    public function findLastBeforeDate(\DateTimeImmutable $date): ?CashStateRegistry
    {
        /** @var CashStateRegistry|null */
        return $this->createQueryBuilder('csr')
            ->where('csr.transactionDate < :date')
            ->setParameter('date', $date)
            ->orderBy('csr.transactionDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all registry entries ordered by transaction date
     *
     * @return CashStateRegistry[]
     */
    public function findAllOrdered(): array
    {
        /** @var CashStateRegistry[] */
        return $this->createQueryBuilder('csr')
            ->orderBy('csr.transactionDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all registry entries ordered by transaction date (most recent first)
     *
     * @return CashStateRegistry[]
     */
    public function findAllOrderedDesc(): array
    {
        /** @var CashStateRegistry[] */
        return $this->createQueryBuilder('csr')
            ->orderBy('csr.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the most recent registry entry
     */
    public function findLatest(): ?CashStateRegistry
    {
        /** @var CashStateRegistry|null */
        return $this->createQueryBuilder('csr')
            ->orderBy('csr.transactionDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Delete all registry entries after a given date
     */
    public function deleteAfterDate(\DateTimeImmutable $date): int
    {
        /** @var int */
        return $this->createQueryBuilder('csr')
            ->delete()
            ->where('csr.transactionDate > :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
