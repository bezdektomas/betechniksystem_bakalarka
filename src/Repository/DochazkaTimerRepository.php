<?php

namespace App\Repository;

use App\Entity\DochazkaTimer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DochazkaTimer>
 */
class DochazkaTimerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DochazkaTimer::class);
    }

    /**
     * Najde aktivní timer uživatele
     */
    public function findActiveForUser(User $user): ?DochazkaTimer
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Smaže timer uživatele
     */
    public function removeForUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
