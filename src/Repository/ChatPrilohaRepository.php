<?php

namespace App\Repository;

use App\Entity\ChatKonverzace;
use App\Entity\ChatPriloha;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChatPrilohaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatPriloha::class);
    }

    /** @return ChatPriloha[] */
    public function findByKonverzace(ChatKonverzace $konverzace, int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.zprava', 'z')
            ->andWhere('z.konverzace = :konverzace')
            ->setParameter('konverzace', $konverzace)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
