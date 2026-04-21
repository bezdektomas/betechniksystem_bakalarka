<?php

namespace App\Repository;

use App\Entity\Faktura;
use App\Entity\User;
use App\Entity\Zakazka;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Faktura>
 */
class FakturaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Faktura::class);
    }

    /**
     * Najde všechny faktury pro danou zakázku
     * 
     * @return Faktura[]
     */
    public function findByZakazka(Zakazka $zakazka): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.zakazka = :zakazka')
            ->setParameter('zakazka', $zakazka)
            ->orderBy('f.datum', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Najde všechny faktury přístupné danému uživateli
     * Admin vidí vše, ostatní pouze faktury ze zakázek, ke kterým mají přístup
     * 
     * @return Faktura[]
     */
    public function findAccessibleByUser(User $user, ?string $search = null, ?string $statusFilter = null): array
    {
        return $this->getAccessibleByUserQueryBuilder($user, $search, $statusFilter)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrací QueryBuilder pro paginator
     */
    public function getAccessibleByUserQueryBuilder(User $user, ?string $search = null, ?string $statusFilter = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.zakazka', 'z')
            ->leftJoin('f.status', 's');

        // Admin vidí vše
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->leftJoin('z.assignedUsers', 'au')
               ->andWhere('z.createdBy = :user OR au = :user')
               ->setParameter('user', $user);
        }

        // Vyhledávání
        if ($search) {
            $qb->andWhere('f.adresa LIKE :search OR z.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtr podle statusu
        if ($statusFilter) {
            $qb->andWhere('s.id = :statusId')
               ->setParameter('statusId', $statusFilter);
        }

        return $qb->orderBy('f.datum', 'DESC');
    }

    /**
     * Sečte celkovou hodnotu faktur pro uživatele
     */
    public function sumTotalByUser(User $user): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('SUM(f.cenaBezDph) as sumBezDph, SUM(f.cenaSDph) as sumSDph')
            ->leftJoin('f.zakazka', 'z');

        // Admin vidí vše
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->leftJoin('z.assignedUsers', 'au')
               ->andWhere('z.createdBy = :user OR au = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Počet faktur podle statusu pro dashboard
     */
    public function countByStatus(User $user): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('s.name, s.color, COUNT(f.id) as count')
            ->leftJoin('f.status', 's')
            ->leftJoin('f.zakazka', 'z')
            ->groupBy('s.id');

        // Admin vidí vše
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->leftJoin('z.assignedUsers', 'au')
               ->andWhere('z.createdBy = :user OR au = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}
