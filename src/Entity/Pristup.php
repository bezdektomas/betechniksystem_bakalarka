<?php

namespace App\Entity;

use App\Repository\PristupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PristupRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Pristup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $popis = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $url = null;

    #[ORM\ManyToOne(inversedBy: 'pristupy')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Zakazka $zakazka = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

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

    // === Veřejné gettery/settery (pro aplikaci) ===
    // Tyto metody jsou aliasy pro Raw metody - data jsou dešifrována listenerem

    public function getPopis(): ?string
    {
        return $this->popis;
    }

    public function setPopis(string $popis): static
    {
        $this->popis = $popis;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    // === Raw metody pro EntityListener ===
    // Tyto metody přistupují přímo k property bez transformace

    public function getPopisRaw(): ?string
    {
        return $this->popis;
    }

    public function setPopisRaw(?string $popis): static
    {
        $this->popis = $popis;
        return $this;
    }

    public function getUsernameRaw(): ?string
    {
        return $this->username;
    }

    public function setUsernameRaw(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getPasswordRaw(): ?string
    {
        return $this->password;
    }

    public function setPasswordRaw(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getUrlRaw(): ?string
    {
        return $this->url;
    }

    public function setUrlRaw(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    // === Ostatní gettery/settery ===

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
