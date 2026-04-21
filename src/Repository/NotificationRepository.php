<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** Posledních $limit notifikací uživatele (přečtené i nepřečtené). */
    public function findRecentByUser(User $user, int $limit = 15): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /** Počet nepřečtených notifikací uživatele. */
    public function countUnreadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Označí jako přečtené všechny notifikace uživatele týkající se daného resource.
     * Volá se automaticky při návštěvě stránky (NotificationReadListener).
     */
    public function markReadByUserAndResource(User $user, string $resourceType, int $resourceId): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', true)
            ->set('n.readAt', ':now')
            ->andWhere('n.user = :user')
            ->andWhere('n.resourceType = :type')
            ->andWhere('n.resourceId = :id')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('type', $resourceType)
            ->setParameter('id', $resourceId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /** Označí všechny nepřečtené notifikace uživatele jako přečtené. */
    public function markAllReadByUser(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', true)
            ->set('n.readAt', ':now')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
