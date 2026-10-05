<?php

declare(strict_types=1);

namespace App\User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: '"user"')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 120)]
    private string $fullName = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $verified = true;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $followedTopics = [];

    /**
     * @param non-empty-string $email
     */
    public function __construct(string $email, string $password)
    {
        $this->email = $email;
        $this->password = $password;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }


    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    /** @return list<string> */
    public function getFollowedTopics(): array
    {
        return array_values(array_unique($this->followedTopics));
    }

    public function followsTopic(string $slug): bool
    {
        return in_array($slug, $this->followedTopics, true);
    }

    public function followTopic(string $slug): void
    {
        if (! $this->followsTopic($slug)) {
            $this->followedTopics[] = $slug;
        }
    }

    public function unfollowTopic(string $slug): void
    {
        $this->followedTopics = array_values(array_filter(
            $this->followedTopics,
            static fn (string $followedSlug): bool => $followedSlug !== $slug,
        ));
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $fullName): void { $this->fullName = $fullName; }
    public function isVerified(): bool { return $this->verified; }
    public function setVerified(bool $verified): void { $this->verified = $verified; }

    public function eraseCredentials(): void
    {
        // no plaintext stored
    }
}
