<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Zakazka;
use App\Entity\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Zakazka>
 */
class ZakazkaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Zakazka::class);
    }

    /**
     * Filtrování a řazení zakázek
     * @return Zakazka[]
     */
    public function findFiltered(?string $statusId, ?string $search, string $sort = 'createdAt', string $direction = 'desc', bool $onlyMine = false, ?User $currentUser = null): array
    {
        return $this->getFilteredQueryBuilder($statusId, $search, $sort, $direction, $onlyMine, $currentUser)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrací QueryBuilder pro paginaci
     */
    public function getFilteredQueryBuilder(?string $statusId, ?string $search, string $sort = 'createdAt', string $direction = 'desc', bool $onlyMine = false, ?User $currentUser = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('z')
            ->leftJoin('z.status', 's')
            ->addSelect('s');

        if ($search) {
            $qb->andWhere('z.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($statusId) {
            $qb->andWhere('z.status = :statusId')
               ->setParameter('statusId', (int) $statusId);
        }

        if ($onlyMine && $currentUser !== null) {
            $qb->andWhere(':currentUser MEMBER OF z.assignedUsers OR z.createdBy = :currentUser')
               ->setParameter('currentUser', $currentUser);
        }

        // Řazení
        $sortField = match ($sort) {
            'price' => 'z.price',
            'name' => 'z.name',
            'realizace' => 'z.realizace',
            default => 'z.createdAt',
        };

        $qb->orderBy($sortField, strtoupper($direction));

        return $qb;
    }

    /**
     * @return Zakazka[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('z')
            ->orderBy('z.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Zakazka[]
     */
    public function findByStatus(Status $status): array
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.status = :status')
            ->setParameter('status', $status)
            ->orderBy('z.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Zakazka[]
     */
    public function findByStatusId(int $statusId): array
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.status = :statusId')
            ->setParameter('statusId', $statusId)
            ->orderBy('z.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vyhledávání podle názvu
     * @return Zakazka[]
     */
    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('z.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Počet zakázek podle statusu
     */
    public function countByStatus(Status $status): int
    {
        return $this->createQueryBuilder('z')
            ->select('COUNT(z.id)')
            ->andWhere('z.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Celková hodnota zakázek podle statusu
     */
    public function sumPriceByStatus(Status $status): float
    {
        $result = $this->createQueryBuilder('z')
            ->select('SUM(z.price)')
            ->andWhere('z.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    /**
     * Zakázky podle statusu a datového rozsahu (podle updatedAt)
     * @return Zakazka[]
     */
    public function findByStatusAndDateRange(Status $status, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.status = :status')
            ->andWhere('z.updatedAt >= :from')
            ->andWhere('z.updatedAt <= :to')
            ->setParameter('status', $status)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('z.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiky pro dashboard
     */
    public function getDashboardStats(): array
    {
        $qb = $this->createQueryBuilder('z')
            ->select('s.id as status_id, s.name as status_name, COUNT(z.id) as count, SUM(z.price) as total')
            ->join('z.status', 's')
            ->groupBy('s.id')
            ->orderBy('s.sortOrder', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Poslední zakázky pro dashboard
     * @return Zakazka[]
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('z')
            ->orderBy('z.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
