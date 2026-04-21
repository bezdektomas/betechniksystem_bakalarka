<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Používá se pro automatické upgrady (rehash) hesla uživatele v průběhu času.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return User[]
     */
    public function findActiveUsers(): array
    {
        return $this->findBy(['isActive' => true], ['createdAt' => 'DESC']);
    }

    /**
     * QueryBuilder pro filtrovaný seznam uživatelů (pro stránkování).
     */
    public function createFilteredQueryBuilder(string $search = '', string $role = '', string $status = ''): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('u.email LIKE :search OR u.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($role !== '') {
            $qb->andWhere('u.roles LIKE :role')
               ->setParameter('role', '%' . $role . '%');
        }

        if ($status !== '') {
            $qb->andWhere('u.isActive = :status')
               ->setParameter('status', $status === 'active');
        }

        return $qb;
    }

    /**
     * Vrátí všechny aktivní adminy.
     *
     * @return User[]
     */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->andWhere('u.isActive = true')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Počet uživatelů s rolí ROLE_ADMIN.
     */
    public function countAdmins(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
