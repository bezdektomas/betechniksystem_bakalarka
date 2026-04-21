<?php

namespace App\Entity;

use App\Repository\ChatReakceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatReakceRepository::class)]
#[ORM\Table(name: 'chat_reakce')]
#[ORM\UniqueConstraint(name: 'uniq_chat_reakce', columns: ['zprava_id', 'user_id', 'emoji'])]
class ChatReakce
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reakce')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatZprava $zprava;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 32)]
    private string $emoji;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(ChatZprava $zprava, User $user, string $emoji)
    {
        $this->zprava    = $zprava;
        $this->user      = $user;
        $this->emoji     = $emoji;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int           { return $this->id; }
    public function getZprava(): ChatZprava { return $this->zprava; }
    public function getUser(): User         { return $this->user; }
    public function getEmoji(): string      { return $this->emoji; }
}
