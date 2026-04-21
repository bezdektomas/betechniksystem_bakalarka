<?php

namespace App\Repository;

use App\Entity\Pristup;
use App\Entity\User;
use App\Entity\Zakazka;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pristup>
 */
class PristupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pristup::class);
    }

    /**
     * Najde všechny přístupy pro danou zakázku
     * 
     * @return Pristup[]
     */
    public function findByZakazka(Zakazka $zakazka): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.zakazka = :zakazka')
            ->setParameter('zakazka', $zakazka)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrací QueryBuilder pro paginaci - přístupy dostupné uživateli
     */
    public function getAccessibleByUserQueryBuilder(User $user, ?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.zakazka', 'z')
            ->addSelect('z');

        // Admin vidí vše
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->leftJoin('z.assignedUsers', 'au')
               ->andWhere('z.createdBy = :user OR au = :user')
               ->setParameter('user', $user);
        }

        // Vyhledávání
        if ($search) {
            $qb->andWhere('p.popis LIKE :search OR p.username LIKE :search OR z.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('p.createdAt', 'DESC');
    }

    /**
     * Najde všechny přístupy přístupné danému uživateli
     * 
     * @return Pristup[]
     */
    public function findAccessibleByUser(User $user, ?string $search = null): array
    {
        return $this->getAccessibleByUserQueryBuilder($user, $search)
            ->getQuery()
            ->getResult();
    }
}
