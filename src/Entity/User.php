<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Uživatel s tímto emailem již existuje.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * @var list<string> Symfony role
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Granulární oprávnění - JSON objekt pro flexibilní permission flags
     * Např.: {"can_manage_users": true, "can_view_invoices": true, "can_edit_orders": false}
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $permissions = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * @var Collection<int, Zakazka>
     */
    #[ORM\OneToMany(targetEntity: Zakazka::class, mappedBy: 'createdBy')]
    private Collection $createdZakazkas;

    /**
     * @var Collection<int, Zakazka>
     */
    #[ORM\ManyToMany(targetEntity: Zakazka::class, mappedBy: 'assignedUsers')]
    private Collection $assignedZakazkas;

    /**
     * @var Collection<int, Dochazka>
     */
    #[ORM\OneToMany(targetEntity: Dochazka::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $dochazky;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?DochazkaTimer $activeTimer = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->createdZakazkas = new ArrayCollection();
        $this->assignedZakazkas = new ArrayCollection();
        $this->dochazky = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    public function setPermissions(?array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->permissions === null) {
            return false;
        }
        return isset($this->permissions[$permission]) && $this->permissions[$permission] === true;
    }

    /**
     * Grant specific permission
     */
    public function grantPermission(string $permission): static
    {
        $permissions = $this->permissions ?? [];
        $permissions[$permission] = true;
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Revoke specific permission
     */
    public function revokePermission(string $permission): static
    {
        if ($this->permissions !== null && isset($this->permissions[$permission])) {
            $permissions = $this->permissions;
            $permissions[$permission] = false;
            $this->permissions = $permissions;
        }
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    /**
     * @return Collection<int, Zakazka>
     */
    public function getCreatedZakazkas(): Collection
    {
        return $this->createdZakazkas;
    }

    /**
     * @return Collection<int, Zakazka>
     */
    public function getAssignedZakazkas(): Collection
    {
        return $this->assignedZakazkas;
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->email ?? 'Neznámý';
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
            $dochazka->setUser($this);
        }
        return $this;
    }

    public function removeDochazka(Dochazka $dochazka): static
    {
        if ($this->dochazky->removeElement($dochazka)) {
            if ($dochazka->getUser() === $this) {
                $dochazka->setUser(null);
            }
        }
        return $this;
    }

    public function getActiveTimer(): ?DochazkaTimer
    {
        return $this->activeTimer;
    }

    public function setActiveTimer(?DochazkaTimer $activeTimer): static
    {
        // unset the owning side of the relation if necessary
        if ($activeTimer === null && $this->activeTimer !== null) {
            $this->activeTimer->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($activeTimer !== null && $activeTimer->getUser() !== $this) {
            $activeTimer->setUser($this);
        }

        $this->activeTimer = $activeTimer;
        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }
}
