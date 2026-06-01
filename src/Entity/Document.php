<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    public const TYPE_FILE = 'file';      // pdf, docx, image, vidéo…
    public const TYPE_LINK = 'link';      // lien externe
    public const TYPE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Titre affiché dans le menu (ex: "FPM - Gestion des indus.pdf")
    #[ORM\Column(length: 255)]
    private string $title;

    // Type logique (pdf, docx, video, image, url...)
    #[ORM\Column(length: 50)]
    private string $documentType;

    // Mime type si tu veux discriminer plus finement (application/pdf, image/png...)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mimeType = null;

    // Chemin dans le filesystem OU URL distante
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $path = null;

    // Pour gérer un lien externe (si TYPE_LINK)
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $externalUrl = null;

    // Lien vers la vidéo de didacticiel (URL du fichier vidéo dans public/uploads/videos)
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $externalVideoUrl = null;

    // Ordre d'affichage dans le dossier
    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    // Dossier parent
    #[ORM\ManyToOne(targetEntity: Folder::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Folder $folder = null;

    // Texte extrait du PDF pour l'indexation et la recherche
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textContent = null;

    // ---- getters / setters ----

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDocumentType(): string { return $this->documentType; }
    public function setDocumentType(string $documentType): self { $this->documentType = $documentType; return $this; }

    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): self { $this->mimeType = $mimeType; return $this; }

    public function getPath(): ?string { return $this->path; }
    public function setPath(?string $path): self { $this->path = $path; return $this; }

    public function getExternalUrl(): ?string { return $this->externalUrl; }
    public function setExternalUrl(?string $externalUrl): self { $this->externalUrl = $externalUrl; return $this; }

    public function getExternalVideoUrl(): ?string { return $this->externalVideoUrl; }
    public function setExternalVideoUrl(?string $externalVideoUrl): self { $this->externalVideoUrl = $externalVideoUrl; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function getFolder(): ?Folder { return $this->folder; }
    public function setFolder(?Folder $folder): self { $this->folder = $folder; return $this; }

    public function getTextContent(): ?string { return $this->textContent; }
    public function setTextContent(?string $textContent): self { $this->textContent = $textContent; return $this; }

    public function isLink(): bool
    {
        return $this->documentType === self::TYPE_LINK;
    }

    public function isPdf(): bool
    {
        return $this->documentType === self::TYPE_FILE && $this->mimeType === 'application/pdf';
    }

    /**
     * Libellé court pour l'admin (PDF, PNG, LIEN…), sans le mot générique « Fichier ».
     */
    public function getShortFormatLabel(): string
    {
        if ($this->isLink()) {
            return 'Lien';
        }
        if ($this->mimeType !== null && str_contains($this->mimeType, '/')) {
            return strtoupper(explode('/', $this->mimeType, 2)[1]);
        }
        if ($this->mimeType !== null) {
            return strtoupper($this->mimeType);
        }

        return '—';
    }

    /**
     * Nom de fichier pour téléchargement / ZIP : basé sur le titre affiché,
     * avec l'extension réelle du fichier stocké (pas le nom internalisé type slug_uniqid.pdf).
     */
    public function getDownloadFilename(): string
    {
        $title = trim($this->title);
        if ($title === '') {
            $title = 'document';
        }

        $ext = '';
        if ($this->path !== null && $this->path !== '') {
            $ext = strtolower(pathinfo(basename(str_replace('\\', '/', $this->path)), PATHINFO_EXTENSION) ?: '');
        }
        if ($ext === '' && $this->mimeType !== null) {
            if ($this->mimeType === 'application/pdf') {
                $ext = 'pdf';
            } elseif (str_contains($this->mimeType, '/')) {
                $piece = strtolower(explode('/', $this->mimeType, 2)[1]);
                $ext = match ($piece) {
                    'jpeg' => 'jpg',
                    default => $piece,
                };
            }
        }
        if ($ext === '') {
            $ext = 'bin';
        }

        $suffix = '.'.$ext;
        $lowerTitle = strtolower($title);
        if (str_ends_with($lowerTitle, $suffix)) {
            $base = substr($title, 0, -\strlen($suffix));
        } else {
            $base = $title;
        }

        $base = trim($base);
        if ($base === '') {
            $base = 'document';
        }

        $base = str_replace(['/', '\\', "\0"], '-', $base);
        $base = preg_replace('/[\x00-\x1F\x7F]/', '', $base) ?? $base;

        return $base.$suffix;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
