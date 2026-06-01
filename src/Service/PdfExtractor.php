<?php

namespace App\Service;

use Smalot\PdfParser\Parser;
use Psr\Log\LoggerInterface;
use Exception;

class PdfExtractor
{
    private Parser $parser;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->parser = new Parser();
        $this->logger = $logger;
    }

    /**
     * Extrait le texte d'un fichier PDF
     * 
     * @param string $filePath Chemin complet du fichier PDF
     * @return string|null Texte extrait ou null en cas d'erreur
     */
    public function extractTextFromPdf(string $filePath): ?string
    {
        try {
            if (!file_exists($filePath)) {
                $this->logger->warning('PDF file not found for text extraction', ['path' => $filePath]);
                return null;
            }

            if (!is_readable($filePath)) {
                $this->logger->warning('PDF file is not readable', ['path' => $filePath]);
                return null;
            }

            // Parser le fichier PDF
            $pdf = $this->parser->parseFile($filePath);
            
            // Extraire le texte de toutes les pages
            $text = '';
            $pages = $pdf->getPages();
            
            foreach ($pages as $page) {
                $text .= $page->getText() . "\n";
            }

            // Nettoyer le texte : supprimer les espaces multiples, les nouvelles lignes excessives
            $cleanText = $this->cleanText($text);

            $this->logger->info('PDF text extraction successful', [
                'path' => $filePath,
                'length' => strlen($cleanText)
            ]);

            return $cleanText ?: null;
        } catch (Exception $e) {
            $this->logger->error('PDF text extraction failed', [
                'path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Nettoie le texte extrait
     */
    private function cleanText(string $text): string
    {
        // Remplacer les nouvelles lignes multiples par une seule
        $text = preg_replace('/\n\n+/', "\n", $text);
        
        // Remplacer les espaces multiples par un seul
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Supprimer les espaces aux début et fin
        $text = trim($text);
        
        return $text;
    }
}
