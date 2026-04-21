<?php

namespace App\Entity;

use App\Repository\DochazkaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DochazkaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Dochazka
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dochazky')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'dochazky')]
    private ?Zakazka $zakazka = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $datum = null;

    #[ORM\Column]
    private int $minuty = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $popis = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getZakazka(): ?Zakazka
    {
        return $this->zakazka;
    }

    public function setZakazka(?Zakazka $zakazka): static
    {
        $this->zakazka = $zakazka;
        return $this;
    }

    public function getDatum(): ?\DateTimeInterface
    {
        return $this->datum;
    }

    public function setDatum(\DateTimeInterface $datum): static
    {
        $this->datum = $datum;
        return $this;
    }

    public function getMinuty(): int
    {
        return $this->minuty;
    }

    public function setMinuty(int $minuty): static
    {
        $this->minuty = $minuty;
        return $this;
    }


    // Odpracovaný čas v hodinach a minutach
    public function getFormattedTime(): string
    {
        $hours = intdiv($this->minuty, 60);
        $mins = $this->minuty % 60;
        
        if ($hours > 0 && $mins > 0) {
            return sprintf('%dh %dm', $hours, $mins);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        } else {
            return sprintf('%dm', $mins);
        }
    }

    // Odpracovaný čas v desetinných hodinách
    public function getHours(): float
    {
        return round($this->minuty / 60, 2);
    }

    public function getPopis(): ?string
    {
        return $this->popis;
    }

    public function setPopis(?string $popis): static
    {
        $this->popis = $popis;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
