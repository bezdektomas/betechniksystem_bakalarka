<?php

namespace App\Repository;

use App\Entity\ChatKonverzace;
use App\Entity\ChatZprava;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChatZpravaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatZprava::class);
    }

    /**
     * Posledních N zpráv v konverzaci (vrácených od nejstarší po nejnovější).
     *
     * @return ChatZprava[]
     */
    public function findByKonverzace(ChatKonverzace $konverzace, int $limit = 50, int $offset = 0): array
    {
        $zpravy = $this->createQueryBuilder('z')
            ->leftJoin('z.reakce', 'r')->addSelect('r')
            ->leftJoin('r.user', 'ru')->addSelect('ru')
            ->andWhere('z.konverzace = :konverzace')
            ->setParameter('konverzace', $konverzace)
            ->orderBy('z.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return array_reverse($zpravy);
    }

    /**
     * Vrací zprávy starší než $beforeId (od nejstarší po nejnovější, maximálně $limit).
     *
     * @return ChatZprava[]
     */
    public function findOlderThan(ChatKonverzace $konverzace, int $beforeId, int $limit = 20): array
    {
        $zpravy = $this->createQueryBuilder('z')
            ->leftJoin('z.reakce', 'r')->addSelect('r')
            ->leftJoin('r.user', 'ru')->addSelect('ru')
            ->andWhere('z.konverzace = :konverzace')
            ->andWhere('z.id < :beforeId')
            ->setParameter('konverzace', $konverzace)
            ->setParameter('beforeId', $beforeId)
            ->orderBy('z.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($zpravy);
    }
}
