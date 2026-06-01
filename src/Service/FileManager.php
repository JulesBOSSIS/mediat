<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Exception;
use Psr\Log\LoggerInterface;

class FileManager
{
    private string $uploadsDir;
    private SluggerInterface $slugger;
    private LoggerInterface $logger;

    public function __construct(
        string $uploadsDir,
        SluggerInterface $slugger,
        LoggerInterface $logger
    ) {
        $this->uploadsDir = $uploadsDir;
        $this->slugger = $slugger;
        $this->logger = $logger;
    }

    /**
     * Upload un fichier et retourne le chemin relatif
     */
    public function uploadFile(UploadedFile $file, string $subfolder = ''): string
    {
        try {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            // Slugify the original filename
            $safeFilename = $this->slugger->slug($originalFilename);
            // Add timestamp + extension to ensure uniqueness
            $newFilename = $safeFilename . '_' . uniqid() . '.' . $file->guessExtension();

            // Créer le dossier de destination s'il n'existe pas
            $targetDir = $this->getTargetDirectory($subfolder);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Déplacer le fichier
            $file->move($targetDir, $newFilename);

            // Retourner le chemin relatif
            $relativePath = $subfolder ? $subfolder . '/' . $newFilename : $newFilename;
            $this->logger->info('File uploaded successfully', ['path' => $relativePath]);

            return $relativePath;
        } catch (Exception $e) {
            $this->logger->error('File upload failed', ['error' => $e->getMessage()]);
            throw new Exception('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }
    }

    /**
     * Supprime un fichier
     */
    public function deleteFile(string $relativePath): bool
    {
        try {
            $fullPath = $this->getFullPath($relativePath);

            if (file_exists($fullPath)) {
                unlink($fullPath);
                $this->logger->info('File deleted successfully', ['path' => $relativePath]);
                return true;
            }

            $this->logger->warning('File not found for deletion', ['path' => $relativePath]);
            return false;
        } catch (Exception $e) {
            $this->logger->error('File deletion failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obtient le chemin complet d'un fichier
     */
    public function getFullPath(string $relativePath): string
    {
        return $this->uploadsDir . '/' . $relativePath;
    }

    /**
     * Obtient le répertoire cible pour un dossier
     */
    private function getTargetDirectory(string $subfolder = ''): string
    {
        if (!$subfolder) {
            return $this->uploadsDir;
        }

        return $this->uploadsDir . '/' . $subfolder;
    }
}
