<?php

namespace App\Entity;

use App\Repository\ChatPrecteniRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatPrecteniRepository::class)]
#[ORM\Table(name: 'chat_precteni')]
#[ORM\UniqueConstraint(name: 'uniq_precteni', columns: ['konverzace_id', 'uzivatel_id'])]
class ChatPrecteni
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatKonverzace $konverzace;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $uzivatel;

    #[ORM\Column]
    private \DateTimeImmutable $precteno_at;

    public function __construct(ChatKonverzace $konverzace, User $uzivatel)
    {
        $this->konverzace = $konverzace;
        $this->uzivatel = $uzivatel;
        $this->precteno_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKonverzace(): ChatKonverzace
    {
        return $this->konverzace;
    }

    public function getUzivatel(): User
    {
        return $this->uzivatel;
    }

    public function getPrecteno_at(): \DateTimeImmutable
    {
        return $this->precteno_at;
    }

    public function touch(): static
    {
        $this->precteno_at = new \DateTimeImmutable();
        return $this;
    }
}
