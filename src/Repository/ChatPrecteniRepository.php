<?php

namespace App\Repository;

use App\Entity\ChatKonverzace;
use App\Entity\ChatPrecteni;
use App\Entity\ChatZprava;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChatPrecteniRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatPrecteni::class);
    }

    /**
     * Označí konverzaci jako přečtenou pro uživatele
     */
    public function markAsRead(ChatKonverzace $konverzace, User $uzivatel): void
    {
        $precteni = $this->findOneBy([
            'konverzace' => $konverzace,
            'uzivatel' => $uzivatel,
        ]);

        if ($precteni) {
            $precteni->touch();
        } else {
            $precteni = new ChatPrecteni($konverzace, $uzivatel);
            $this->getEntityManager()->persist($precteni);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Spočítá počet nepřečtených zpráv v jednotlivých konverzacích pro daného uživatele.
     * Vrací pole ve formátu [konverzace_id => počet].
     *
     * @param ChatKonverzace[] $konverzace
     * @return array<int, int>
     */
    public function countUnreadByConversations(array $konverzace, User $uzivatel): array
    {
        if (empty($konverzace)) {
            return [];
        }

        $ids = array_map(fn($k) => $k->getId(), $konverzace);

        $conn = $this->getEntityManager()->getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            SELECT z.konverzace_id, COUNT(z.id) AS cnt
            FROM chat_zprava z
            LEFT JOIN chat_precteni p ON p.konverzace_id = z.konverzace_id AND p.uzivatel_id = ?
            WHERE z.konverzace_id IN ({$placeholders})
              AND z.autor_id != ?
              AND (p.precteno_at IS NULL OR z.created_at > p.precteno_at)
            GROUP BY z.konverzace_id
        ";

        $params = [$uzivatel->getId(), ...$ids, $uzivatel->getId()];
        $rows = $conn->fetchAllAssociative($sql, $params);

        $result = array_fill_keys($ids, 0);
        foreach ($rows as $row) {
            $result[(int) $row['konverzace_id']] = (int) $row['cnt'];
        }

        return $result;
    }
}
