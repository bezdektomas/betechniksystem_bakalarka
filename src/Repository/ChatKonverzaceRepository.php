<?php

namespace App\Repository;

use App\Entity\ChatKonverzace;
use App\Entity\User;
use App\Entity\Zakazka;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChatKonverzaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatKonverzace::class);
    }

    /**
     * Konverzace, jejichž je uživatel členem, seřazené podle času poslední zprávy.
     *
     * @return ChatKonverzace[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.clenove', 'u')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->orderBy('k.posledniZpravaAt', 'DESC')
            ->addOrderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Najde existující přímý chat mezi dvěma uživateli.
     */
    public function findPrimyChat(User $user1, User $user2): ?ChatKonverzace
    {
        $candidates = $this->createQueryBuilder('k')
            ->join('k.clenove', 'u')
            ->andWhere('k.typ = :typ')
            ->andWhere('u = :user1')
            ->setParameter('typ', ChatKonverzace::TYP_PRIMY)
            ->setParameter('user1', $user1)
            ->getQuery()
            ->getResult();

        foreach ($candidates as $k) {
            if ($k->maClen($user2)) {
                return $k;
            }
        }

        return null;
    }

    /**
     * Najde existující chat k zakázce, nebo vrátí null.
     */
    public function findZakazkaChat(Zakazka $zakazka): ?ChatKonverzace
    {
        return $this->findOneBy([
            'typ' => ChatKonverzace::TYP_ZAKAZKA,
            'zakazka' => $zakazka,
        ]);
    }

    /**
     * Synchronizuje členy chatu tak, aby odpovídali aktuálním účastníkům zakázky (tvůrce + assignedUsers).
     * Nic neprovede, pokud pro danou zakázku zatím žádný chat neexistuje.
     */
    public function syncZakazkaMembers(Zakazka $zakazka): void
    {
        $konverzace = $this->findZakazkaChat($zakazka);
        if (!$konverzace) {
            return;
        }

        $expected = [];
        if ($zakazka->getCreatedBy()) {
            $expected[$zakazka->getCreatedBy()->getId()] = $zakazka->getCreatedBy();
        }
        foreach ($zakazka->getAssignedUsers() as $u) {
            $expected[$u->getId()] = $u;
        }

        foreach ($expected as $user) {
            if (!$konverzace->maClen($user)) {
                $konverzace->addClen($user);
            }
        }

        foreach ($konverzace->getClenove() as $clen) {
            if (!isset($expected[$clen->getId()])) {
                $konverzace->removeClen($clen);
            }
        }

        $this->getEntityManager()->flush();
    }
}
