<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\Contribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contribution>
 */
class ContributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contribution::class);
    }

    /**
     * @return array<int, Contribution>
     */
    public function findByClass(ClassRoom $classRoom): array
    {
        /** @var array<int, Contribution> $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.classRoom = :classRoom')
            ->setParameter('classRoom', $classRoom->getId(), 'ulid')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<int, Contribution>
     */
    public function findActiveByClass(ClassRoom $classRoom): array
    {
        /** @var array<int, Contribution> $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.classRoom = :classRoom')
            ->andWhere('c.dueAt IS NULL OR c.dueAt > :now')
            ->setParameter('classRoom', $classRoom->getId(), 'ulid')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('c.dueAt', 'ASC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<int, Contribution>
     */
    public function findOverdueByClass(ClassRoom $classRoom): array
    {
        /** @var array<int, Contribution> $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.classRoom = :classRoom')
            ->andWhere('c.dueAt IS NOT NULL')
            ->andWhere('c.dueAt < :now')
            ->setParameter('classRoom', $classRoom->getId(), 'ulid')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('c.dueAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function save(Contribution $contribution): void
    {
        $this->getEntityManager()
            ->persist($contribution);
        $this->getEntityManager()
            ->flush();
    }

    public function remove(Contribution $contribution): void
    {
        $this->getEntityManager()
            ->remove($contribution);
        $this->getEntityManager()
            ->flush();
    }
}
