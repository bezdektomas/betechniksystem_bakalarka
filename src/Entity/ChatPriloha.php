<?php

namespace App\Entity;

use App\Repository\ChatPrilohaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatPrilohaRepository::class)]
#[ORM\Table(name: 'chat_priloha')]
class ChatPriloha
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'prilohy')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatZprava $zprava;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 255)]
    private string $storedName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(ChatZprava $zprava, string $originalName, string $storedName, string $mimeType, int $size)
    {
        $this->zprava = $zprava;
        $this->originalName = $originalName;
        $this->storedName = $storedName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getZprava(): ChatZprava { return $this->zprava; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStoredName(): string { return $this->storedName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'originalName' => $this->originalName,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
        ];
    }
}
