<?php

namespace App\Controller;

use App\Entity\Document;
use App\Repository\CommentRepository;
use App\Repository\DocumentViewRepository;
use App\Repository\FavoriteRepository;
use App\Service\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, EntityManagerInterface $entityManager, FavoriteRepository $favoriteRepository): Response
    {
        $query = $request->query->get('query', '');
        $results = [];
        $popularDocuments = [];
        $user = $this->getUser();
        
        if (!empty($query)) {
            $documentRepository = $entityManager->getRepository(Document::class);
            $documents = $documentRepository->searchByQuery($query);
            $searchTerms = $documentRepository->parseSearchTerms($query);

            $titleMatches = [];
            $contentMatches = [];

            foreach ($documents as $document) {
                if ($document->isPdf() && $document->getFolder() && $document->getFolder()->isAccessibleBy($user)) {
                    $isFavorite = $favoriteRepository->isFavorite($user, $document);
                    $result = [
                        'id' => $document->getId(),
                        'title' => $document->getTitle(),
                        'type' => $document->getDocumentType(),
                        'folder' => $document->getFolder()->getName(),
                        'folderId' => $document->getFolder()->getId(),
                        'folderPath' => $this->generateUrl('app_folder_view', ['id' => $document->getFolder()->getId()]),
                        'path' => $this->generateUrl('app_document_view', ['id' => $document->getId()]),
                        'documentType' => $document->getDocumentType(),
                        'mimeType' => $document->getMimeType(),
                        'mimeTypeFormatted' => $this->formatMimeType($document->getMimeType()),
                        'excerpt' => $this->truncate($document->getTitle(), 100),
                        'contentExcerpt' => $this->buildSearchExcerpt($document->getTextContent(), $query, $searchTerms),
                        'isFavorite' => $isFavorite,
                    ];

                    if ($this->isTitleSearchMatch($document->getTitle(), $query, $searchTerms)) {
                        $titleMatches[] = $result;
                    } else {
                        $contentMatches[] = $result;
                    }
                }
            }

            $results = array_merge($titleMatches, $contentMatches);
        } else {
            // Récupérer les 3 documents avec le plus de vues (ou les 3 premiers)
            $topDocuments = $entityManager->getRepository(Document::class)->findTopViewedDocuments(3);
            
            foreach ($topDocuments as $document) {
                if ($document->isPdf() && $document->getFolder() && $document->getFolder()->isAccessibleBy($user)) {
                    $isFavorite = $favoriteRepository->isFavorite($user, $document);
                    $popularDocuments[] = [
                        'id' => $document->getId(),
                        'title' => $document->getTitle(),
                        'type' => $document->getDocumentType(),
                        'folder' => $document->getFolder()->getName(),
                        'folderId' => $document->getFolder()->getId(),
                        'folderPath' => $this->generateUrl('app_folder_view', ['id' => $document->getFolder()->getId()]),
                        'path' => $this->generateUrl('app_document_view', ['id' => $document->getId()]),
                        'documentType' => $document->getDocumentType(),
                        'mimeType' => $document->getMimeType(),
                        'mimeTypeFormatted' => $this->formatMimeType($document->getMimeType()),
                        'excerpt' => $this->truncate($document->getTitle(), 100),
                        'isFavorite' => $isFavorite
                    ];
                }
            }
        }
        
        return $this->render('search/results.html.twig', [
            'query' => $query,
            'results' => $results,
            'total' => count($results),
            'popularDocuments' => $popularDocuments,
            'user' => $user
        ]);
    }

    /**
     * @param list<string> $tokens
     */
    private function isTitleSearchMatch(string $title, string $rawQuery, array $tokens): bool
    {
        $phrase = trim($rawQuery);
        if ($phrase !== '' && stripos($title, $phrase) !== false) {
            return true;
        }
        foreach ($tokens as $tok) {
            if (stripos($title, $tok) === false) {
                return false;
            }
        }

        return $tokens !== [];
    }

    /**
     * Extrait un passage du texte PDF autour du premier terme trouvé.
     *
     * @param list<string> $tokens
     */
    private function buildSearchExcerpt(?string $text, string $rawQuery, array $tokens): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        $needles = array_values(array_filter(array_unique(array_merge(
            [trim($rawQuery)],
            $tokens
        ))));
        usort($needles, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            $pos = mb_stripos($text, $needle, 0, 'UTF-8');
            if ($pos !== false) {
                $before = 90;
                $after = 140;
                $start = max(0, $pos - $before);
                $matchLen = mb_strlen($needle, 'UTF-8');
                $snippet = mb_substr($text, $start, $before + $matchLen + $after, 'UTF-8');
                $snippet = preg_replace('/\s+/u', ' ', $snippet);
                $snippet = trim((string) $snippet);
                if ($snippet === '') {
                    return null;
                }
                $prefix = $start > 0 ? '… ' : '';
                $suffix = ($start + mb_strlen($snippet, 'UTF-8')) < mb_strlen($text, 'UTF-8') ? ' …' : '';

                return $prefix.$snippet.$suffix;
            }
        }

        return null;
    }

    /**
     * Tronque un texte à la longueur spécifiée
     */
    private function truncate(string $text, int $length = 100): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '...';
    }

    /**
     * Formate le mimeType en affichant seulement la partie après le /
     * Ex: application/pdf -> pdf, image/png -> png
     */
    private function formatMimeType(?string $mimeType): ?string
    {
        if (!$mimeType) {
            return null;
        }
        
        if (strpos($mimeType, '/') !== false) {
            return strtoupper(explode('/', $mimeType)[1]);
        }
        
        return strtoupper($mimeType);
    }

    #[Route('/document/{id}', name: 'app_document_view')]
    #[IsGranted('ROLE_USER')]
    public function viewDocument(
        Document $document,
        FavoriteRepository $favoriteRepository,
        DocumentViewRepository $documentViewRepository,
        CommentRepository $commentRepository
    ): Response {
        // Rejeter les documents qui ne sont pas des PDF
        if (!$document->isPdf()) {
            throw $this->createNotFoundException('Ce document n\'est pas disponible');
        }

        // Si c'est un lien externe, rediriger
        if ($document->isLink()) {
            return $this->redirect($document->getPath());
        }

        $user = $this->getUser();
        $isFavorite = $favoriteRepository->isFavorite($user, $document);
        
        // Enregistrer la visualisation si elle n'est pas trop récente
        $documentViewRepository->recordViewIfNeeded($document, $user);

        $commentRepository->markRootCommentRepliesAsReadForUserOnDocument($user, $document);

        return $this->render('document/view.html.twig', [
            'document' => $document,
            'isFavorite' => $isFavorite,
        ]);
    }

    #[Route('/document/{id}/download', name: 'app_document_download')]
    #[IsGranted('ROLE_USER')]
    public function downloadDocument(Document $document, FileManager $fileManager): Response
    {
        // Rejeter les documents qui ne sont pas des PDF
        if (!$document->isPdf()) {
            throw $this->createNotFoundException('Ce document n\'est pas disponible');
        }

        // Vérifier que c'est un fichier
        if ($document->getDocumentType() !== Document::TYPE_FILE) {
            throw $this->createNotFoundException('Ce document n\'est pas un fichier');
        }

        $filePath = $fileManager->getFullPath($document->getPath());

        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier n\'existe pas');
        }

        $response = new BinaryFileResponse($filePath);
        
        // Forcer le téléchargement pour tous les fichiers sauf les PDFs et images
        $isPdf = $document->getMimeType() === 'application/pdf';
        $isImage = $document->getMimeType() && strpos($document->getMimeType(), 'image/') === 0;
        $isVideo = $document->getMimeType() && strpos($document->getMimeType(), 'video/') === 0;
        $isAudio = $document->getMimeType() && strpos($document->getMimeType(), 'audio/') === 0;
        $isText = $document->getMimeType() && (
            strpos($document->getMimeType(), 'text/') === 0 ||
            $document->getMimeType() === 'application/json' ||
            strpos($document->getMimeType(), 'xml') !== false
        );
        
        $disposition = ($isPdf || $isImage || $isVideo || $isAudio || $isText) 
            ? ResponseHeaderBag::DISPOSITION_INLINE 
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        
        $downloadName = $document->getDownloadFilename();
        $response->setContentDisposition(
            $disposition,
            $downloadName,
            $this->asciiFilenameFallback($downloadName)
        );

        // Définir le MIME type s'il est disponible
        if ($document->getMimeType()) {
            $response->headers->set('Content-Type', $document->getMimeType());
        }

        return $response;
    }

    #[Route('/document/{id}/file', name: 'app_document_file')]
    #[IsGranted('ROLE_USER')]
    public function getDocumentFile(Document $document, FileManager $fileManager): Response
    {
        // Vérifier que c'est un fichier
        if ($document->getDocumentType() !== Document::TYPE_FILE) {
            throw $this->createNotFoundException('Ce document n\'est pas un fichier');
        }

        $filePath = $fileManager->getFullPath($document->getPath());

        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier n\'existe pas');
        }

        $filename = $document->getDownloadFilename();
        $response = new BinaryFileResponse($filePath);
        
        // Forcer l'affichage inline pour les types compatibles
        $isPdf = $document->getMimeType() === 'application/pdf';
        $isImage = $document->getMimeType() && strpos($document->getMimeType(), 'image/') === 0;
        $isVideo = $document->getMimeType() && strpos($document->getMimeType(), 'video/') === 0;
        $isAudio = $document->getMimeType() && strpos($document->getMimeType(), 'audio/') === 0;
        $isText = $document->getMimeType() && (
            strpos($document->getMimeType(), 'text/') === 0 ||
            $document->getMimeType() === 'application/json' ||
            strpos($document->getMimeType(), 'xml') !== false
        );
        
        $fallback = $this->asciiFilenameFallback($filename);
        if ($isPdf || $isImage || $isVideo || $isAudio || $isText) {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename, $fallback);
        } else {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, $fallback);
        }

        // Définir le MIME type
        if ($document->getMimeType()) {
            $response->headers->set('Content-Type', $document->getMimeType());
        }

        // Headers supplémentaires pour le cache
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

        return $response;
    }

    #[Route('/documents/download-multiple', name: 'app_download_pdfs')]
    #[IsGranted('ROLE_USER')]
    public function downloadMultiplePdfs(Request $request, FileManager $fileManager, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les IDs des documents et le nom du dossier
        $documentIds = $request->request->all()['document_ids'] ?? [];
        $folderName = $request->request->get('folder_name', 'documents');
        
        if (empty($documentIds)) {
            throw $this->createNotFoundException('Aucun document sélectionné');
        }

        // Récupérer les documents
        $repository = $entityManager->getRepository(Document::class);
        $documents = [];
        
        foreach ($documentIds as $id) {
            $doc = $repository->find($id);
            if ($doc && $doc->isPdf() && $doc->getDocumentType() === Document::TYPE_FILE) {
                $documents[] = $doc;
            }
        }

        if (empty($documents)) {
            throw $this->createNotFoundException('Aucun PDF valide trouvé');
        }

        // Créer un zip
        $zipPath = sys_get_temp_dir() . '/pdfs_' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier ZIP');
        }

        // Ajouter les PDFs au zip (noms = titre + extension, sans suffixe unique du stockage)
        $takenZipNames = [];
        foreach ($documents as $document) {
            $filePath = $fileManager->getFullPath($document->getPath());

            if (!file_exists($filePath) || !is_readable($filePath)) {
                continue;
            }

            $entryName = $document->getDownloadFilename();
            $candidate = $entryName;
            $stem = pathinfo($entryName, PATHINFO_FILENAME) ?: 'document';
            $ext = pathinfo($entryName, PATHINFO_EXTENSION);
            $suffix = $ext !== '' ? '.'.$ext : '';

            $i = 2;
            while (isset($takenZipNames[$candidate])) {
                $candidate = sprintf('%s (%d)%s', $stem, $i, $suffix);
                ++$i;
            }

            $takenZipNames[$candidate] = true;
            $zip->addFile($filePath, $candidate);
        }

        $zip->close();

        // Générer un nom de fichier parlant avec le nom du dossier
        $zipFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $folderName) . '.zip';

        // Télécharger le zip
        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $zipFileName
        );
        $response->headers->set('Content-Type', 'application/zip');

        // Supprimer le fichier après le téléchargement
        register_shutdown_function(function() use ($zipPath) {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        });

        return $response;
    }

    /**
     * Nom de fichier ASCII pour clients qui ne gèrent pas filename* UTF-8 dans Content-Disposition.
     */
    private function asciiFilenameFallback(string $filename): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        if ($ascii !== false && $ascii !== '') {
            $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii) ?? '';
        }

        if ($ascii === false || $ascii === '') {
            $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?? '';
        }

        return $ascii !== '' ? $ascii : 'download';
    }
}