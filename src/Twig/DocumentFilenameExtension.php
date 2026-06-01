<?php

namespace App\Twig;

use App\Entity\Document;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DocumentFilenameExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('document_safe_filename', [$this, 'getSafeFilename']),
        ];
    }

    public function getSafeFilename(Document $document): string
    {
        $extension = $document->getPath() ? pathinfo($document->getPath(), PATHINFO_EXTENSION) : 'pdf';
        $extension = $extension ?: 'pdf';

        // Retirer l'extension du titre s'il en contient une (évite "document.pdf.pdf")
        $title = preg_replace('/\.(pdf|PDF|docx?|xlsx?|jpg|jpeg|png|gif)$/i', '', $document->getTitle());
        if (function_exists('transliterator_transliterate')) {
            $title = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $title) ?: $title;
        } else {
            $title = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        }
        $title = str_replace(' ', '_', $title);
        $title = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title);
        $title = preg_replace('/_+/', '_', $title);
        $title = trim($title, '_');

        return ($title ?: 'document') . '.' . strtolower($extension);
    }
}
