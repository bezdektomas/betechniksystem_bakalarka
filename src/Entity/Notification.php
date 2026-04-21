<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(columns: ['user_id', 'is_read', 'created_at'], name: 'idx_notification_user_unread')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    /** Typ resource, ke kterému notifikace patří (konverzace, zakazka, faktura, …) */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $resourceType = null;

    #[ORM\Column(nullable: true)]
    private ?int $resourceId = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): static { $this->body = $body; return $this; }

    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $link): static { $this->link = $link; return $this; }

    public function getResourceType(): ?string { return $this->resourceType; }
    public function setResourceType(?string $resourceType): static { $this->resourceType = $resourceType; return $this; }

    public function getResourceId(): ?int { return $this->resourceId; }
    public function setResourceId(?int $resourceId): static { $this->resourceId = $resourceId; return $this; }

    public function isRead(): bool { return $this->isRead; }
    public function setRead(bool $isRead): static { $this->isRead = $isRead; return $this; }

    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function setReadAt(?\DateTimeImmutable $readAt): static { $this->readAt = $readAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'body'      => $this->body,
            'link'      => $this->link,
            'isRead'    => $this->isRead,
            'createdAt' => $this->createdAt->format('c'),
        ];
    }
}
