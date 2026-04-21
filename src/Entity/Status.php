<?php

namespace App\Entity;

use App\Repository\StatusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusRepository::class)]
class Status
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    private ?string $color = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    /**
     * @var Collection<int, Zakazka>
     */
    #[ORM\OneToMany(targetEntity: Zakazka::class, mappedBy: 'status')]
    private Collection $zakazkas;

    public function __construct()
    {
        $this->zakazkas = new ArrayCollection();
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
     * @return Collection<int, Zakazka>
     */
    public function getZakazkas(): Collection
    {
        return $this->zakazkas;
    }

    public function addZakazka(Zakazka $zakazka): static
    {
        if (!$this->zakazkas->contains($zakazka)) {
            $this->zakazkas->add($zakazka);
            $zakazka->setStatus($this);
        }
        return $this;
    }

    public function removeZakazka(Zakazka $zakazka): static
    {
        if ($this->zakazkas->removeElement($zakazka)) {
            if ($zakazka->getStatus() === $this) {
                $zakazka->setStatus(null);
            }
        }
        return $this;
    }

    public function getBadgeClasses(): string
    {
        return $this->color ?? 'bg-slate-100 text-slate-800';
    }
}
