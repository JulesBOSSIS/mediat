<?php

namespace App\Entity;

use App\Repository\FolderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: FolderRepository::class)]
class Folder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nom affiché dans le menu (ex : "CTIMUT", "Menu", "1.Paramètres", ...)
    #[ORM\Column(length: 255)]
    private string $name;

    // Pour les URLs si besoin (optionnel mais pratique)
    #[ORM\Column(length: 255, unique: false, nullable: true)]
    private ?string $slug = null;

    // Ordre d'affichage parmi les frères
    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    // Dossier parent (null si racine)
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: true)]
    private ?Folder $parent = null;

    // Sous-dossiers
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, orphanRemoval: false)]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    // Fichiers contenus dans ce dossier
    #[ORM\OneToMany(mappedBy: 'folder', targetEntity: Document::class, orphanRemoval: false)]
    #[ORM\OrderBy(['position' => 'ASC', 'title' => 'ASC'])]
    private Collection $documents;

    // Rôles requis pour accéder à ce dossier (null = public, sinon JSON array)
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $requiredRoles = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    // ---- getters / setters classiques ----

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): self { $this->parent = $parent; return $this; }

    /** @return Collection<int, Folder> */
    public function getChildren(): Collection { return $this->children; }

    public function addChild(Folder $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Folder $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Document> */
    public function getDocuments(): Collection { return $this->documents; }

    public function addDocument(Document $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setFolder($this);
        }
        return $this;
    }

    public function removeDocument(Document $document): self
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getFolder() === $this) {
                $document->setFolder(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getRequiredRoles(): ?array
    {
        return $this->requiredRoles;
    }

    public function setRequiredRoles(?array $requiredRoles): self
    {
        $this->requiredRoles = $requiredRoles;
        return $this;
    }

    /**
     * Vérifie si l'utilisateur a accès à ce dossier
     */
    public function isAccessibleBy(UserInterface $user): bool
    {
        // Si pas de restriction, le dossier est public
        if ($this->requiredRoles === null || empty($this->requiredRoles)) {
            return true;
        }

        // Vérifier si l'utilisateur a au moins un des rôles requis
        foreach ($this->requiredRoles as $requiredRole) {
            if ($requiredRole === 'ROLE_CTIA') {
                $requiredRole = 'ROLE_PARTNER';
            }
            if (in_array($requiredRole, $user->getRoles(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compte le nombre total de documents dans ce dossier et tous ses sous-dossiers
     */
    public function countTotalDocuments(): int
    {
        $count = $this->documents->count();

        // Ajouter les documents des sous-dossiers récursivement
        foreach ($this->children as $child) {
            $count += $child->countTotalDocuments();
        }

        return $count;
    }
}

