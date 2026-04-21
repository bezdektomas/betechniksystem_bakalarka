<?php

namespace App\Repository;

use App\Entity\Dochazka;
use App\Entity\User;
use App\Entity\Zakazka;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dochazka>
 */
class DochazkaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dochazka::class);
    }

    /**
     * Vrátí QueryBuilder pro docházku uživatele
     */
    public function createQueryBuilderForUser(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.datum', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC');
    }

    /**
     * Vrátí QueryBuilder pro veškerou docházku (admin)
     */
    public function createQueryBuilderAll(): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->leftJoin('d.zakazka', 'z')
            ->addSelect('z')
            ->orderBy('d.datum', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC');
    }

    /**
     * Vrátí QueryBuilder pro docházku k zakázce
     */
    public function createQueryBuilderForZakazka(Zakazka $zakazka): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->where('d.zakazka = :zakazka')
            ->setParameter('zakazka', $zakazka)
            ->orderBy('d.datum', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC');
    }

    /**
     * Vrátí celkové minuty pro uživatele za den
     */
    public function getTotalMinutesForUserAndDate(User $user, \DateTimeInterface $date): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.minuty)')
            ->where('d.user = :user')
            ->andWhere('d.datum = :datum')
            ->setParameter('user', $user)
            ->setParameter('datum', $date->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Vrátí celkové minuty pro uživatele v období
     */
    public function getTotalMinutesForUserInPeriod(User $user, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.minuty)')
            ->where('d.user = :user')
            ->andWhere('d.datum >= :from')
            ->andWhere('d.datum <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Vrátí souhrn docházky po zakázkách pro uživatele
     */
    public function getSummaryByZakazkaForUser(User $user): array
    {
        $results = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.zakazka) as zakazkaId, SUM(d.minuty) as totalMinutes')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->groupBy('d.zakazka')
            ->orderBy('totalMinutes', 'DESC')
            ->getQuery()
            ->getResult();

        // Načteme zakázky
        $zakazkaIds = array_filter(array_column($results, 'zakazkaId'));
        $zakazky = [];
        
        if (!empty($zakazkaIds)) {
            $em = $this->getEntityManager();
            $zakazkyEntities = $em->getRepository(Zakazka::class)->findBy(['id' => $zakazkaIds]);
            foreach ($zakazkyEntities as $z) {
                $zakazky[$z->getId()] = $z;
            }
        }

        $summary = [];
        foreach ($results as $row) {
            $summary[] = [
                'zakazka' => $row['zakazkaId'] ? ($zakazky[$row['zakazkaId']] ?? null) : null,
                'totalMinutes' => (int) $row['totalMinutes'],
            ];
        }

        return $summary;
    }

    /**
     * Vrátí souhrn docházky po uživatelích (pro admina)
     */
    public function getSummaryByUser(): array
    {
        $results = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.user) as userId, SUM(d.minuty) as totalMinutes')
            ->groupBy('d.user')
            ->orderBy('totalMinutes', 'DESC')
            ->getQuery()
            ->getResult();

        // Načteme uživatele
        $userIds = array_column($results, 'userId');
        $users = [];
        
        if (!empty($userIds)) {
            $em = $this->getEntityManager();
            $usersEntities = $em->getRepository(User::class)->findBy(['id' => $userIds]);
            foreach ($usersEntities as $u) {
                $users[$u->getId()] = $u;
            }
        }

        $summary = [];
        foreach ($results as $row) {
            $summary[] = [
                'user' => $users[$row['userId']] ?? null,
                'totalMinutes' => (int) $row['totalMinutes'],
            ];
        }

        return $summary;
    }

    /**
     * Vrátí docházku uživatele za aktuální týden
     */
    public function getThisWeekForUser(User $user): array
    {
        $now = new \DateTime();
        $monday = (clone $now)->modify('monday this week');
        $sunday = (clone $now)->modify('sunday this week');

        return $this->createQueryBuilder('d')
            ->leftJoin('d.zakazka', 'z')
            ->addSelect('z')
            ->where('d.user = :user')
            ->andWhere('d.datum >= :monday')
            ->andWhere('d.datum <= :sunday')
            ->setParameter('user', $user)
            ->setParameter('monday', $monday->format('Y-m-d'))
            ->setParameter('sunday', $sunday->format('Y-m-d'))
            ->orderBy('d.datum', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrátí docházku uživatele za dnešek
     */
    public function getTodayForUser(User $user): array
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('d')
            ->leftJoin('d.zakazka', 'z')
            ->addSelect('z')
            ->where('d.user = :user')
            ->andWhere('d.datum = :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
