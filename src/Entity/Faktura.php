<?php

namespace App\Entity;

use App\Repository\FakturaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FakturaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Faktura
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adresa = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalFilename = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $cenaBezDph = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $cenaSDph = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $cenaBezDaneZPrijmu = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $datum = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $poznamka = null;

    #[ORM\ManyToOne(inversedBy: 'faktury')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StatusFaktura $status = null;

    #[ORM\ManyToOne(inversedBy: 'faktury')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Zakazka $zakazka = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdresa(): ?string
    {
        return $this->adresa;
    }

    public function setAdresa(?string $adresa): static
    {
        $this->adresa = $adresa;
        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getCenaBezDph(): ?float
    {
        return $this->cenaBezDph !== null ? (float) $this->cenaBezDph : null;
    }

    public function setCenaBezDph(?float $cenaBezDph): static
    {
        $this->cenaBezDph = $cenaBezDph !== null ? (string) $cenaBezDph : null;
        return $this;
    }

    public function getCenaSDph(): ?float
    {
        return $this->cenaSDph !== null ? (float) $this->cenaSDph : null;
    }

    public function setCenaSDph(?float $cenaSDph): static
    {
        $this->cenaSDph = $cenaSDph !== null ? (string) $cenaSDph : null;
        return $this;
    }

    public function getCenaBezDaneZPrijmu(): ?float
    {
        return $this->cenaBezDaneZPrijmu !== null ? (float) $this->cenaBezDaneZPrijmu : null;
    }

    public function setCenaBezDaneZPrijmu(?float $cenaBezDaneZPrijmu): static
    {
        $this->cenaBezDaneZPrijmu = $cenaBezDaneZPrijmu !== null ? (string) $cenaBezDaneZPrijmu : null;
        return $this;
    }

    public function getDatum(): ?\DateTimeInterface
    {
        return $this->datum;
    }

    public function setDatum(?\DateTimeInterface $datum): static
    {
        $this->datum = $datum;
        return $this;
    }

    public function getPoznamka(): ?string
    {
        return $this->poznamka;
    }

    public function setPoznamka(?string $poznamka): static
    {
        $this->poznamka = $poznamka;
        return $this;
    }

    public function getStatus(): ?StatusFaktura
    {
        return $this->status;
    }

    public function setStatus(?StatusFaktura $status): static
    {
        $this->status = $status;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function calculateCenaSDph(): void
    {
        if ($this->cenaBezDph !== null) {
            $this->cenaSDph = (string) (((float) $this->cenaBezDph) * 1.21);
        }
    }

    public function calculateCenaBezDaneZPrijmu(): void
    {
        if ($this->cenaBezDph !== null) {
            $this->cenaBezDaneZPrijmu = (string) (((float) $this->cenaBezDph) * 0.79);
        }
    }

    public function calculatePrices(): void
    {
        $this->calculateCenaSDph();
        $this->calculateCenaBezDaneZPrijmu();
    }
}
