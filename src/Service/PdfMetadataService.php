<?php

namespace App\Service;

use setasign\Fpdi\Fpdi;
use Psr\Log\LoggerInterface;

class PdfMetadataService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Met à jour les métadonnées (titre) d'un fichier PDF.
     * Réécrit le PDF avec le nouveau titre pour que Chrome affiche le bon nom.
     *
     * @param string $filePath Chemin complet vers le fichier PDF
     * @param string $title    Titre à définir dans les métadonnées
     * @return bool True si succès, false en cas d'erreur (le fichier reste inchangé)
     */
    public function updateMetadata(string $filePath, string $title): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->logger->warning('PdfMetadataService: fichier inaccessible', ['path' => $filePath]);

            return false;
        }

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($filePath);

            if ($pageCount < 1) {
                $this->logger->warning('PdfMetadataService: PDF sans pages', ['path' => $filePath]);

                return false;
            }

            // Définir les métadonnées (UTF-8 pour les accents français)
            $pdf->SetTitle($title, true);
            $pdf->SetCreator('MediaT', true);

            for ($i = 1; $i <= $pageCount; $i++) {
                $templateId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage(
                    $size['width'] > $size['height'] ? 'L' : 'P',
                    [$size['width'], $size['height']]
                );
                $pdf->useTemplate($templateId);
            }

            // Écrire dans un fichier temporaire puis remplacer (évite conflit lecture/écriture)
            $tempPath = $filePath . '.tmp.' . uniqid();
            $pdf->Output('F', $tempPath);

            if (!rename($tempPath, $filePath)) {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                $this->logger->error('PdfMetadataService: impossible de remplacer le fichier', ['path' => $filePath]);

                return false;
            }

            $this->logger->info('PdfMetadataService: métadonnées mises à jour', [
                'path' => $filePath,
                'title' => $title,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('PdfMetadataService: erreur lors de la mise à jour des métadonnées', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
