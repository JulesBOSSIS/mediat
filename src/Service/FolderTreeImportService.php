<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Folder;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class FolderTreeImportService
{
    /** @var array<string, int> */
    private array $folderPositionCursor = [];

    /** @var array<string, int> */
    private array $documentPositionCursor = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileManager $fileManager,
        private PdfExtractor $pdfExtractor,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array{folders?: mixed, files?: mixed} $manifest
     * @param list<UploadedFile>                   $pdfFiles same order as manifest.files
     */
    public function import(?Folder $mountParent, array $manifest, array $pdfFiles): FolderTreeImportResult
    {
        $this->folderPositionCursor = [];
        $this->documentPositionCursor = [];

        $folderPaths = $manifest['folders'] ?? null;
        $fileEntries = $manifest['files'] ?? null;

        if (!is_array($folderPaths)) {
            throw new InvalidArgumentException('Manifeste invalide : la clé « folders » doit être un tableau.');
        }
        if (!is_array($fileEntries)) {
            throw new InvalidArgumentException('Manifeste invalide : la clé « files » doit être un tableau.');
        }

        /** @var list<string> $normalizedFolders */
        $normalizedFolders = [];
        foreach ($folderPaths as $path) {
            if (!is_string($path)) {
                throw new InvalidArgumentException('Manifeste invalide : chaque dossier doit être une chaîne de chemin.');
            }
            $normalizedFolders[] = $this->normalizeRelativePath($path);
        }

        /** @var list<array{path: string, title: string}> $normalizedFiles */
        $normalizedFiles = [];
        foreach ($fileEntries as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Manifeste invalide : chaque fichier doit être un objet.');
            }
            $p = $row['path'] ?? null;
            $t = $row['title'] ?? null;
            if (!is_string($p) || !is_string($t)) {
                throw new InvalidArgumentException('Manifeste invalide : chaque fichier doit avoir « path » et « title » en texte.');
            }
            $np = $this->normalizeRelativePath($p);
            if (!str_ends_with(strtolower($np), '.pdf')) {
                throw new InvalidArgumentException(sprintf('Seuls les fichiers PDF sont acceptés : « %s ».', $np));
            }
            if (strlen($t) > 255) {
                throw new InvalidArgumentException(sprintf('Titre trop long pour « %s » (255 caractères max).', $np));
            }
            $normalizedFiles[] = ['path' => $np, 'title' => $t];
        }

        if (count($normalizedFiles) !== count($pdfFiles)) {
            throw new InvalidArgumentException(sprintf(
                'Nombre de PDF incohérent : %d entrée(s) dans le manifeste, %d fichier(s) envoyé(s).',
                count($normalizedFiles),
                count($pdfFiles)
            ));
        }

        /** @var array<string, true> $folderSet Chemins dossiers : manifeste + préfixes dérivés (évite les écarts client / navigateur). */
        $folderSet = [];
        foreach ($normalizedFolders as $fp) {
            $folderSet[$fp] = true;
            $this->registerAncestorFolderPaths($fp, $folderSet);
        }
        foreach ($normalizedFiles as $fileRow) {
            $this->registerAncestorFolderPaths($fileRow['path'], $folderSet);
        }

        $normalizedFolders = array_keys($folderSet);
        usort($normalizedFolders, function (string $a, string $b): int {
            $depthCmp = substr_count($a, '/') <=> substr_count($b, '/');
            if ($depthCmp !== 0) {
                return $depthCmp;
            }

            return $a <=> $b;
        });

        /** @var array<string, Folder> $folderMap */
        $folderMap = [];

        foreach ($normalizedFolders as $path) {
            $parentPathStr = $this->parentPath($path);
            $parentEntity = $parentPathStr === '' ? $mountParent : ($folderMap[$parentPathStr] ?? null);
            if ($parentPathStr !== '' && $parentEntity === null) {
                throw new InvalidArgumentException(sprintf('Dossier parent introuvable pour « %s ».', $path));
            }

            $name = basename(str_replace('\\', '/', $path));
            if ($name === '' || $name === '.' || $name === '..') {
                throw new InvalidArgumentException(sprintf('Nom de dossier invalide dans « %s ».', $path));
            }
            if (strlen($name) > 255) {
                throw new InvalidArgumentException(sprintf('Nom de dossier trop long (255 caractères max) dans « %s ».', $path));
            }

            $folder = new Folder();
            $folder->setName($name);
            $folder->setSlug(strtolower((string) $this->slugger->slug($name)));
            $folder->setPosition($this->nextFolderPosition($parentEntity));
            $folder->setParent($parentEntity);

            $this->entityManager->persist($folder);
            $folderMap[$path] = $folder;
        }

        $uploadedRelativePaths = [];
        $documentsCreated = 0;

        try {
            foreach ($normalizedFiles as $index => $fileRow) {
                /** @var UploadedFile $uploaded */
                $uploaded = $pdfFiles[$index];
                if (!$uploaded instanceof UploadedFile || !$uploaded->isValid()) {
                    throw new InvalidArgumentException(sprintf('Fichier #%d : upload invalide.', $index + 1));
                }

                $ext = strtolower($uploaded->getClientOriginalExtension() ?: '');
                if ($ext !== 'pdf') {
                    throw new InvalidArgumentException(sprintf('Le fichier « %s » n’est pas un PDF.', $uploaded->getClientOriginalName()));
                }

                $dirPath = $this->parentPath($fileRow['path']);
                $folderEntity = $dirPath === '' ? $mountParent : ($folderMap[$dirPath] ?? null);
                if ($folderEntity === null) {
                    throw new InvalidArgumentException(sprintf('Dossier cible introuvable pour « %s ».', $fileRow['path']));
                }

                $mimeType = $uploaded->getMimeType() ?: 'application/pdf';
                if ($mimeType !== 'application/pdf') {
                    $mimeType = 'application/pdf';
                }

                $relativeStoragePath = $this->fileManager->uploadFile($uploaded, 'documents');
                $uploadedRelativePaths[] = $relativeStoragePath;

                $document = new Document();
                $document->setTitle($fileRow['title']);
                $document->setDocumentType(Document::TYPE_FILE);
                $document->setMimeType($mimeType);
                $document->setPath($relativeStoragePath);
                $document->setFolder($folderEntity);
                $document->setPosition($this->nextDocumentPosition($folderEntity));

                $fullPath = $this->fileManager->getFullPath($relativeStoragePath);
                $textContent = $this->pdfExtractor->extractTextFromPdf($fullPath);
                if ($textContent !== null && $textContent !== '') {
                    $document->setTextContent($textContent);
                }

                $this->entityManager->persist($document);
                ++$documentsCreated;
            }

            $this->entityManager->flush();
        } catch (\Throwable $e) {
            foreach ($uploadedRelativePaths as $rel) {
                $this->fileManager->deleteFile($rel);
            }
            throw $e;
        }

        $this->logger->info('Folder tree import completed', [
            'folders' => count($folderMap),
            'documents' => $documentsCreated,
            'mount_parent_id' => $mountParent?->getId(),
        ]);

        return new FolderTreeImportResult(
            foldersCreated: count($folderMap),
            documentsCreated: $documentsCreated,
        );
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, '/');
        if ($path === '') {
            throw new InvalidArgumentException('Chemin relatif vide.');
        }

        $parts = explode('/', $path);
        foreach ($parts as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException(sprintf('Chemin non autorisé : « %s ».', $path));
            }
        }

        return implode('/', $parts);
    }

    private function parentPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $pos = strrpos($path, '/');

        if ($pos === false) {
            return '';
        }

        return substr($path, 0, $pos);
    }

    /**
     * Enregistre tous les préfixes « dossier » d'un chemin relatif (parents du fichier ou du dossier).
     *
     * @param array<string, true> $folderSet
     */
    private function registerAncestorFolderPaths(string $relativeFileOrFolderPath, array &$folderSet): void
    {
        $dir = $this->parentPath($relativeFileOrFolderPath);
        while ($dir !== '') {
            $folderSet[$dir] = true;
            $dir = $this->parentPath($dir);
        }
    }

    private function folderCursorKey(?Folder $parent): string
    {
        if ($parent === null) {
            return 'root';
        }

        return 'folder_'.spl_object_id($parent);
    }

    private function nextFolderPosition(?Folder $parent): int
    {
        $key = $this->folderCursorKey($parent);
        if (!isset($this->folderPositionCursor[$key])) {
            $this->folderPositionCursor[$key] = $this->maxExistingFolderPosition($parent) + 1;
        }

        $pos = $this->folderPositionCursor[$key];
        ++$this->folderPositionCursor[$key];

        return $pos;
    }

    private function maxExistingFolderPosition(?Folder $parent): int
    {
        // Entité non persistée : aucune ligne en base ne peut encore la référencer.
        if ($parent !== null && $parent->getId() === null) {
            return -1;
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(MAX(f.position), -1)')
            ->from(Folder::class, 'f');

        if ($parent === null) {
            $qb->where('f.parent IS NULL');
        } else {
            $qb->where('f.parent = :p')->setParameter('p', $parent);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function nextDocumentPosition(Folder $folder): int
    {
        $key = 'doc_'.spl_object_id($folder);
        if (!isset($this->documentPositionCursor[$key])) {
            $this->documentPositionCursor[$key] = $this->maxExistingDocumentPosition($folder) + 1;
        }

        $pos = $this->documentPositionCursor[$key];
        ++$this->documentPositionCursor[$key];

        return $pos;
    }

    private function maxExistingDocumentPosition(Folder $folder): int
    {
        if ($folder->getId() === null) {
            return -1;
        }

        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(MAX(d.position), -1)')
            ->from(Document::class, 'd')
            ->where('d.folder = :f')
            ->setParameter('f', $folder)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
