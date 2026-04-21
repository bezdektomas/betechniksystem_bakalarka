<?php

namespace App\Entity;

use App\Repository\UserFcmTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserFcmTokenRepository::class)]
#[ORM\Table(name: 'user_fcm_token')]
#[ORM\UniqueConstraint(columns: ['user_id', 'token'])]
class UserFcmToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 512)]
    private string $token;

    #[ORM\Column(length: 20)]
    private string $platform = 'web';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(User $user, string $token, string $platform = 'web')
    {
        $this->user      = $user;
        $this->token     = $token;
        $this->platform  = $platform;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getToken(): string { return $this->token; }
    public function getPlatform(): string { return $this->platform; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }

    public function touchLastUsed(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }
}
