<?php

namespace App\Entity;

use App\Repository\StatusFakturaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusFakturaRepository::class)]
class StatusFaktura
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $color = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * @var Collection<int, Faktura>
     */
    #[ORM\OneToMany(targetEntity: Faktura::class, mappedBy: 'status')]
    private Collection $faktury;

    public function __construct()
    {
        $this->faktury = new ArrayCollection();
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * Vrací Tailwind CSS třídy pro badge podle barvy
     */
    public function getBadgeClasses(): string
    {
        return match ($this->color) {
            'blue' => 'bg-blue-100 text-blue-800',
            'yellow' => 'bg-yellow-100 text-yellow-800',
            'green' => 'bg-green-100 text-green-800',
            'red' => 'bg-red-100 text-red-800',
            'gray' => 'bg-slate-100 text-slate-600',
            'orange' => 'bg-[rgba(241,97,1,0.15)] text-[rgb(241,97,1)]',
            'purple' => 'bg-purple-100 text-purple-800',
            'pink' => 'bg-pink-100 text-pink-800',
            'indigo' => 'bg-indigo-100 text-indigo-800',
            'cyan' => 'bg-cyan-100 text-cyan-800',
            default => 'bg-slate-100 text-slate-800',
        };
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
            $faktura->setStatus($this);
        }
        return $this;
    }

    public function removeFaktura(Faktura $faktura): static
    {
        if ($this->faktury->removeElement($faktura)) {
            if ($faktura->getStatus() === $this) {
                $faktura->setStatus(null);
            }
        }
        return $this;
    }
}
