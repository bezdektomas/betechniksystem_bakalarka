<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserFcmToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserFcmTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFcmToken::class);
    }

    /** @return string[] */
    public function findTokensByUsers(array $users): array
    {
        if (empty($users)) return [];

        return $this->createQueryBuilder('t')
            ->select('t.token')
            ->andWhere('t.user IN (:users)')
            ->setParameter('users', $users)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findByUserAndToken(User $user, string $token): ?UserFcmToken
    {
        return $this->findOneBy(['user' => $user, 'token' => $token]);
    }

    /** Remove tokens older than 60 days that haven't been used */
    public function removeExpired(): void
    {
        $cutoff = new \DateTimeImmutable('-60 days');
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.createdAt < :cutoff')
            ->andWhere('t.lastUsedAt IS NULL OR t.lastUsedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
