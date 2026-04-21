<?php

namespace App\Entity;

use App\Repository\ZakazkaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZakazkaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Zakazka
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $realizace = null;

    #[ORM\ManyToOne(inversedBy: 'createdZakazkas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'zakazkas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Status $status = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'assignedZakazkas')]
    #[ORM\JoinTable(name: 'zakazka_user')]
    private Collection $assignedUsers;

    /**
     * @var Collection<int, Faktura>
     */
    #[ORM\OneToMany(targetEntity: Faktura::class, mappedBy: 'zakazka', orphanRemoval: true)]
    private Collection $faktury;

    /**
     * @var Collection<int, Pristup>
     */
    #[ORM\OneToMany(targetEntity: Pristup::class, mappedBy: 'zakazka', orphanRemoval: true)]
    private Collection $pristupy;

    /**
     * @var Collection<int, Dochazka>
     */
    #[ORM\OneToMany(targetEntity: Dochazka::class, mappedBy: 'zakazka')]
    private Collection $dochazky;

    public function __construct()
    {
        $this->assignedUsers = new ArrayCollection();
        $this->faktury = new ArrayCollection();
        $this->pristupy = new ArrayCollection();
        $this->dochazky = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getPriceInt(): ?int
    {
        return $this->price !== null ? (int) $this->price : null;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function getRealizace(): ?\DateTimeInterface
    {
        return $this->realizace;
    }

    public function setRealizace(?\DateTimeInterface $realizace): static
    {
        $this->realizace = $realizace;
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

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAssignedUsers(): Collection
    {
        return $this->assignedUsers;
    }

    public function addAssignedUser(User $user): static
    {
        if (!$this->assignedUsers->contains($user)) {
            $this->assignedUsers->add($user);
        }
        return $this;
    }

    public function removeAssignedUser(User $user): static
    {
        $this->assignedUsers->removeElement($user);
        return $this;
    }

    public function clearAssignedUsers(): static
    {
        $this->assignedUsers->clear();
        return $this;
    }

    /**
     * @return Collection<int, Faktura>
     */
    public function getFaktury(): Collection
    {
        return $this->faktury;
    }

    public function addFaktura(Faktura $faktura): static
    {
        if (!$this->faktury->contains($faktura)) {
            $this->faktury->add($faktura);
            $faktura->setZakazka($this);
        }
        return $this;
    }

    public function removeFaktura(Faktura $faktura): static
    {
        if ($this->faktury->removeElement($faktura)) {
            if ($faktura->getZakazka() === $this) {
                $faktura->setZakazka(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Pristup>
     */
    public function getPristupy(): Collection
    {
        return $this->pristupy;
    }

    public function addPristup(Pristup $pristup): static
    {
        if (!$this->pristupy->contains($pristup)) {
            $this->pristupy->add($pristup);
            $pristup->setZakazka($this);
        }
        return $this;
    }

    public function removePristup(Pristup $pristup): static
    {
        if ($this->pristupy->removeElement($pristup)) {
            if ($pristup->getZakazka() === $this) {
                $pristup->setZakazka(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Dochazka>
     */
    public function getDochazky(): Collection
    {
        return $this->dochazky;
    }

    public function addDochazka(Dochazka $dochazka): static
    {
        if (!$this->dochazky->contains($dochazka)) {
            $this->dochazky->add($dochazka);
            $dochazka->setZakazka($this);
        }
        return $this;
    }

    public function removeDochazka(Dochazka $dochazka): static
    {
        if ($this->dochazky->removeElement($dochazka)) {
            if ($dochazka->getZakazka() === $this) {
                $dochazka->setZakazka(null);
            }
        }
        return $this;
    }

    /**
     * Celkový odpracovaný čas na zakázce v minutách
     */
    public function getTotalWorkedMinutes(): int
    {
        $total = 0;
        foreach ($this->dochazky as $dochazka) {
            $total += $dochazka->getMinuty();
        }
        return $total;
    }

    /**
     * Formátovaný celkový čas
     */
    public function getFormattedTotalTime(): string
    {
        $minutes = $this->getTotalWorkedMinutes();
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        
        if ($hours > 0 && $mins > 0) {
            return sprintf('%dh %dm', $hours, $mins);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        } else {
            return sprintf('%dm', $mins);
        }
    }

    /**
     * Zkontroluje, zda má uživatel přístup k této zakázce
     */
    public function hasUserAccess(User $user): bool
    {
        // Admin má přístup vždy
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }
        
        // Tvůrce zakázky má přístup
        if ($this->createdBy === $user) {
            return true;
        }
        
        // Přiřazení uživatelé mají přístup
        return $this->assignedUsers->contains($user);
    }
}
