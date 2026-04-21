<?php

namespace App\Entity;

use App\Repository\DochazkaTimerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Aktivní stopky pro měření času práce
 * Každý uživatel může mít max 1 aktivní timer
 */
#[ORM\Entity(repositoryClass: DochazkaTimerRepository::class)]
class DochazkaTimer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'activeTimer')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Zakazka $zakazka = null;

    /**
     * Kdy byly stopky naposledy spuštěny
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $startedAt = null;

    /**
     * Kdy byly stopky pozastaveny (null = běží)
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pausedAt = null;

    /**
     * Nahromaděný čas v minutách (z předchozích pause/resume cyklů)
     */
    #[ORM\Column]
    private int $accumulatedMinutes = 0;

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

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getPausedAt(): ?\DateTimeImmutable
    {
        return $this->pausedAt;
    }

    public function setPausedAt(?\DateTimeImmutable $pausedAt): static
    {
        $this->pausedAt = $pausedAt;
        return $this;
    }

    public function getAccumulatedMinutes(): int
    {
        return $this->accumulatedMinutes;
    }

    public function setAccumulatedMinutes(int $accumulatedMinutes): static
    {
        $this->accumulatedMinutes = $accumulatedMinutes;
        return $this;
    }

    /**
     * Je timer aktivní (běží)?
     */
    public function isRunning(): bool
    {
        return $this->pausedAt === null;
    }

    /**
     * Je timer pozastaven?
     */
    public function isPaused(): bool
    {
        return $this->pausedAt !== null;
    }

    /**
     * Spočítá celkový čas v minutách (včetně aktuálně běžícího)
     */
    public function getTotalMinutes(): int
    {
        $total = $this->accumulatedMinutes;
        
        if ($this->isRunning()) {
            // Přičteme čas od posledního startu/resume
            $now = new \DateTimeImmutable();
            $diff = $now->getTimestamp() - $this->startedAt->getTimestamp();
            $total += intdiv($diff, 60);
        }
        
        return $total;
    }

    /**
     * Vrátí formátovaný čas "Xh Ym"
     */
    public function getFormattedTime(): string
    {
        $minutes = $this->getTotalMinutes();
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
     * Pozastaví timer
     */
    public function pause(): void
    {
        if ($this->isRunning()) {
            $now = new \DateTimeImmutable();
            $diff = $now->getTimestamp() - $this->startedAt->getTimestamp();
            $this->accumulatedMinutes += intdiv($diff, 60);
            $this->pausedAt = $now;
        }
    }

    /**
     * Obnoví timer z pauzy
     */
    public function resume(): void
    {
        if ($this->isPaused()) {
            $this->startedAt = new \DateTimeImmutable();
            $this->pausedAt = null;
        }
    }
}
