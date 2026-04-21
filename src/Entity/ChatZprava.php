<?php

namespace App\Entity;

use App\Repository\ChatZpravaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatZpravaRepository::class)]
#[ORM\Table(name: 'chat_zprava')]
class ChatZprava
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $obsah;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    #[ORM\ManyToOne(inversedBy: 'zpravy')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ChatKonverzace $konverzace = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $autor = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ChatZprava $replyTo = null;

    #[ORM\OneToMany(mappedBy: 'zprava', targetEntity: ChatPriloha::class, cascade: ['persist', 'remove'])]
    private Collection $prilohy;

    #[ORM\OneToMany(mappedBy: 'zprava', targetEntity: ChatReakce::class, cascade: ['persist', 'remove'])]
    private Collection $reakce;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->prilohy   = new ArrayCollection();
        $this->reakce    = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObsah(): string
    {
        return $this->obsah;
    }

    public function setObsah(string $obsah): static
    {
        $this->obsah = $obsah;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEditedAt(): ?\DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function setEditedAt(?\DateTimeImmutable $editedAt): static
    {
        $this->editedAt = $editedAt;
        return $this;
    }

    public function getKonverzace(): ?ChatKonverzace
    {
        return $this->konverzace;
    }

    public function setKonverzace(?ChatKonverzace $konverzace): static
    {
        $this->konverzace = $konverzace;
        return $this;
    }

    public function getAutor(): ?User
    {
        return $this->autor;
    }

    public function setAutor(?User $autor): static
    {
        $this->autor = $autor;
        return $this;
    }

    public function getReplyTo(): ?ChatZprava
    {
        return $this->replyTo;
    }

    public function setReplyTo(?ChatZprava $replyTo): static
    {
        $this->replyTo = $replyTo;
        return $this;
    }

    public function getPrilohy(): Collection
    {
        return $this->prilohy;
    }

    public function getReakce(): Collection
    {
        return $this->reakce;
    }

    /**
     * Returns reactions grouped by emoji, with count and user info.
     *
     * @return array<int, array{emoji: string, count: int, userIds: int[], userNames: string[]}>
     */
    public function getGroupedReakce(): array
    {
        $grouped = [];
        foreach ($this->reakce as $r) {
            $emoji = $r->getEmoji();
            if (!isset($grouped[$emoji])) {
                $grouped[$emoji] = ['emoji' => $emoji, 'count' => 0, 'userIds' => [], 'userNames' => []];
            }
            $grouped[$emoji]['count']++;
            $grouped[$emoji]['userIds'][]   = $r->getUser()->getId();
            $grouped[$emoji]['userNames'][] = $r->getUser()->getDisplayName();
        }
        return array_values($grouped);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'obsah' => $this->obsah,
            'createdAt' => $this->createdAt->format('c'),
            'autor' => [
                'id' => $this->autor->getId(),
                'name' => $this->autor->getDisplayName(),
                'avatar' => $this->autor->getProfilePicture(),
            ],
            'replyTo' => $this->replyTo ? [
                'id' => $this->replyTo->getId(),
                'obsah' => mb_substr($this->replyTo->getObsah(), 0, 120),
                'autorName' => $this->replyTo->getAutor()->getDisplayName(),
            ] : null,
            'prilohy' => $this->prilohy->map(fn($p) => $p->toArray())->toArray(),
            'reakce'  => $this->getGroupedReakce(),
        ];
    }
}
