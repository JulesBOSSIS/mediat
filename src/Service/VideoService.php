<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;

class VideoService
{
    private string $videosDir;
    private LoggerInterface $logger;

    public function __construct(
        string $videosDir,
        LoggerInterface $logger
    ) {
        $this->videosDir = $videosDir;
        $this->logger = $logger;
    }

    /**
     * Retourne la liste des vidéos disponibles dans le dossier public/uploads/videos
     * Format: ['test.mp4' => 'test.mp4', ...]
     * La clé et la valeur contiennent juste le nom du fichier (sans le chemin)
     */
    public function getAvailableVideos(): array
    {
        $videos = [];

        try {
            // Créer le dossier s'il n'existe pas
            if (!is_dir($this->videosDir)) {
                mkdir($this->videosDir, 0755, true);
                return $videos;
            }

            // Scanner les fichiers vidéo dans le dossier
            $allowedExtensions = ['mp4', 'webm', 'mov', 'avi', 'flv', 'mkv'];
            $files = scandir($this->videosDir);

            if ($files === false) {
                return $videos;
            }

            foreach ($files as $file) {
                // Ignorer les dossiers et fichiers cachés
                if ($file === '.' || $file === '..' || is_dir($this->videosDir . '/' . $file)) {
                    continue;
                }

                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                // Vérifier l'extension
                if (!in_array($extension, $allowedExtensions)) {
                    continue;
                }

                // Ajouter à la liste avec juste le nom du fichier
                $videos[$file] = $file;
            }

            // Trier alphabétiquement
            asort($videos);

            $this->logger->info('Videos list retrieved', ['count' => count($videos)]);

            return $videos;
        } catch (Exception $e) {
            $this->logger->error('Failed to get videos list', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
