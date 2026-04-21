<?php

namespace App\Entity;

use App\Repository\ChatKonverzaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatKonverzaceRepository::class)]
#[ORM\Table(name: 'chat_konverzace')]
class ChatKonverzace
{
    const TYP_PRIMY = 'PRIMY';
    const TYP_ZAKAZKA = 'ZAKAZKA';
    const TYP_SKUPINA = 'SKUPINA';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $typ = self::TYP_PRIMY;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nazev = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $posledniZpravaAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Zakazka $zakazka = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $vytvoril = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'chat_konverzace_uzivatel')]
    private Collection $clenove;

    #[ORM\OneToMany(targetEntity: ChatZprava::class, mappedBy: 'konverzace', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $zpravy;

    #[ORM\ManyToOne(targetEntity: ChatZprava::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ChatZprava $pinnedZprava = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->clenove = new ArrayCollection();
        $this->zpravy = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTyp(): string
    {
        return $this->typ;
    }

    public function setTyp(string $typ): static
    {
        $this->typ = $typ;
        return $this;
    }

    public function getNazev(): ?string
    {
        return $this->nazev;
    }

    public function setNazev(?string $nazev): static
    {
        $this->nazev = $nazev;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPosledniZpravaAt(): ?\DateTimeImmutable
    {
        return $this->posledniZpravaAt;
    }

    public function setPosledniZpravaAt(?\DateTimeImmutable $posledniZpravaAt): static
    {
        $this->posledniZpravaAt = $posledniZpravaAt;
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

    public function getVytvoril(): ?User
    {
        return $this->vytvoril;
    }

    public function setVytvoril(?User $vytvoril): static
    {
        $this->vytvoril = $vytvoril;
        return $this;
    }

    public function getClenove(): Collection
    {
        return $this->clenove;
    }

    public function addClen(User $user): static
    {
        if (!$this->clenove->contains($user)) {
            $this->clenove->add($user);
        }
        return $this;
    }

    public function removeClen(User $user): static
    {
        $this->clenove->removeElement($user);
        return $this;
    }

    public function maClen(User $user): bool
    {
        return $this->clenove->contains($user);
    }

    public function getZpravy(): Collection
    {
        return $this->zpravy;
    }

    public function getPosledniZprava(): ?ChatZprava
    {
        if ($this->zpravy->isEmpty()) {
            return null;
        }
        return $this->zpravy->last();
    }

    public function getDisplayName(User $currentUser): string
    {
        if ($this->nazev) {
            return $this->nazev;
        }
        if ($this->typ === self::TYP_ZAKAZKA && $this->zakazka) {
            return $this->zakazka->getName();
        }
        if ($this->typ === self::TYP_PRIMY) {
            foreach ($this->clenove as $clen) {
                if ($clen->getId() !== $currentUser->getId()) {
                    return $clen->getDisplayName();
                }
            }
        }
        return 'Skupinový chat';
    }

    public function getInitial(User $currentUser): string
    {
        return mb_strtoupper(mb_substr($this->getDisplayName($currentUser), 0, 1));
    }

    public function getPinnedZprava(): ?ChatZprava
    {
        return $this->pinnedZprava;
    }

    public function setPinnedZprava(?ChatZprava $pinnedZprava): static
    {
        $this->pinnedZprava = $pinnedZprava;
        return $this;
    }

    public function getOtherMember(User $currentUser): ?User
    {
        foreach ($this->clenove as $clen) {
            if ($clen->getId() !== $currentUser->getId()) {
                return $clen;
            }
        }
        return null;
    }
}
