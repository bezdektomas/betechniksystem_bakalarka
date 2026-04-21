<?php

namespace App\Repository;

use App\Entity\StatusFaktura;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StatusFaktura>
 */
class StatusFakturaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatusFaktura::class);
    }

    /**
     * Vrací všechny statusy seřazené podle sortOrder
     * 
     * @return StatusFaktura[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Najde status podle názvu
     */
    public function findByName(string $name): ?StatusFaktura
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Vrací výchozí status pro novou fakturu
     */
    public function findDefaultStatus(): ?StatusFaktura
    {
        return $this->findOneBy([], ['sortOrder' => 'ASC']);
    }
}
