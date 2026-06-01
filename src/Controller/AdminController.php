<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Folder;
use App\Entity\User;
use App\Form\DocumentFormType;
use App\Form\EditUserFormType;
use App\Form\FolderFormType;
use App\Form\UserFormType;
use App\Repository\CommentRepository;
use App\Repository\DocumentViewRepository;
use App\Service\FileManager;
use App\Service\FolderTreeImportService;
use App\Service\PdfExtractor;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

// Contrôleur pour l'administration (utilisateurs, dossiers, documents)

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // Page d'accueil de l'administration
    #[Route('/', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', []);
    }

    // Guide d'administration
    #[Route('/guide', name: 'app_admin_guide')]
    public function guide(): Response
    {
        return $this->render('admin/guide.html.twig', []);
    }

    // ============ USERS MANAGEMENT ============

    // Création d'un nouvel utilisateur, UserPasswordHasherInterface pour le hachage du mot de passe et EntityManagerInterface pour la gestion des entités
    #[Route('/users/create', name: 'app_admin_create_user')]
    public function createUser(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // On crée une nouvelle instance de User
        $user = new User();
        // On crée un formulaire avec validation requise pour la création
        $form = $this->createForm(UserFormType::class, $user);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Hacher le mot de passe avant de le stocker
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Persister l'utilisateur en base de données et flush (exécuter la requête)
            $entityManager->persist($user);
            $entityManager->flush();

            // Ajouter un message flash de succès
            $this->addFlash('success', sprintf('Utilisateur %s créé avec succès avec le rôle %s', $user->getEmail(), $this->getRoleLabel($user->getRoles()[0])));

            // Rediriger vers la liste des utilisateurs après création
            return $this->redirectToRoute('app_admin_list_users');
        }

        return $this->render('admin/create_user.html.twig', [
            'form' => $form,
        ]);
    }

    // Liste des utilisateurs
    #[Route('/users', name: 'app_admin_list_users')]
    public function listUsers(EntityManagerInterface $entityManager): Response
    {
        // Récupérer tous les utilisateurs (méthode findAll() fournie par Doctrine de base)
        $users = $entityManager->getRepository(User::class)->findAll();

        // Rendre la vue avec la liste des utilisateurs pour l'affichage
        return $this->render('admin/list_users.html.twig', [
            'users' => $users,
        ]);
    }

    // Édition d'un utilisateur existant, en passant l'utilisateur (User $user) via l'auto-paramétrage de Symfony
    #[Route('/users/{id}/edit', name: 'app_admin_edit_user')]
    public function editUser(
        User $user,
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(EditUserFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            
            // Mettre à jour le mot de passe seulement s'il a été fourni
            // Besoin d'un traitement spécial pour le mot de passe car il est hashé avant stockage en base de données
            if ($plainPassword) {
                $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            }

            // Mettre à jour l'utilisateur en base de données
            $entityManager->flush();

            // Ajouter un message flash de succès
            $this->addFlash('success', sprintf('Utilisateur %s modifié avec succès', $user->getEmail()));

            // Rediriger vers la liste des utilisateurs après modification
            return $this->redirectToRoute('app_admin_list_users');
        }

        return $this->render('admin/edit_user.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    // Suppression d'un utilisateur
    #[Route('/users/{id}/delete', name: 'app_admin_delete_user', methods: ['POST'])]
    public function deleteUser(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier le token CSRF pour la sécurité (éviter les attaques CSRF)
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Utilisateur %s supprimé avec succès', $user->getEmail()));
        }

        return $this->redirectToRoute('app_admin_list_users');
    }

    // Obtenir le label lisible d'un rôle, par exemple le rôle ROLE_ADMIN devient "Administrateur"
    private function getRoleLabel(string $role): string
    {
        return match($role) {
            User::ROLE_USER => 'Utilisateur',
            User::ROLE_PARTNER => 'Partenaire',
            User::ROLE_ADMIN => 'Administrateur',
            default => $role,
        };
    }

    // ============ END USERS MANAGEMENT ============

    // ============ FOLDERS MANAGEMENT ============

    // Liste des dossiers racines
    #[Route('/folders', name: 'app_admin_list_folders')]
    public function listFolders(EntityManagerInterface $entityManager): Response
    {
        // Récupérer les dossiers racines (ceux sans parent)
        $folders = $entityManager->getRepository(Folder::class)->findBy(['parent' => null], ['position' => 'ASC']);

        $allFolders = $entityManager->getRepository(Folder::class)->findAll();
        $importParentChoices = [];
        foreach ($allFolders as $f) {
            $importParentChoices[] = [
                'id' => $f->getId(),
                'label' => $this->buildFolderAdminLabel($f),
            ];
        }
        usort($importParentChoices, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $this->render('admin/folders/list.html.twig', [
            'folders' => $folders,
            'import_parent_folder_choices' => $importParentChoices,
        ]);
    }

    #[Route('/folders/import-tree', name: 'app_admin_import_folder_tree', methods: ['POST'])]
    public function importFolderTree(
        Request $request,
        EntityManagerInterface $entityManager,
        FolderTreeImportService $folderTreeImportService,
        LoggerInterface $logger
    ): JsonResponse {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('import_folder_tree', (string) $token)) {
            return new JsonResponse(['ok' => false, 'error' => 'Jeton de sécurité invalide ou expiré.'], 403);
        }

        $manifestRaw = $request->request->get('manifest');
        if (!is_string($manifestRaw) || $manifestRaw === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Manifeste JSON manquant.'], 400);
        }

        try {
            /** @var array<string, mixed>|null $manifest */
            $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['ok' => false, 'error' => 'Manifeste JSON illisible.'], 400);
        }

        if (!is_array($manifest)) {
            return new JsonResponse(['ok' => false, 'error' => 'Manifeste invalide.'], 400);
        }

        $mountParent = null;
        $parentIdRaw = $request->request->get('parent_folder_id');
        if ($parentIdRaw !== null && $parentIdRaw !== '') {
            $parentId = (int) $parentIdRaw;
            $mountParent = $entityManager->getRepository(Folder::class)->find($parentId);
            if (!$mountParent) {
                return new JsonResponse(['ok' => false, 'error' => 'Dossier parent introuvable.'], 400);
            }
        }

        $uploadedBag = $request->files->get('pdfs');
        /** @var array<int, UploadedFile> $pdfFiles */
        $pdfFiles = [];
        if ($uploadedBag instanceof UploadedFile) {
            $pdfFiles = [$uploadedBag];
        } elseif (is_array($uploadedBag)) {
            $pdfFiles = array_values(array_filter($uploadedBag, static fn ($f) => $f instanceof UploadedFile));
        }

        try {
            $result = $folderTreeImportService->import($mountParent, $manifest, $pdfFiles);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $logger->error('Échec import arborescence dossiers', ['exception' => $e]);

            return new JsonResponse(['ok' => false, 'error' => 'Une erreur inattendue est survenue pendant l’import.'], 500);
        }

        return new JsonResponse([
            'ok' => true,
            'foldersCreated' => $result->foldersCreated,
            'documentsCreated' => $result->documentsCreated,
        ]);
    }

    // Création d'un nouveau dossier, avec possibilité de définir un dossier parent via un paramètre GET "parentId"
    #[Route('/folders/create', name: 'app_admin_create_folder')]
    public function createFolder(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $parentId = $request->query->get('parentId');
        $parent = null;
        
        // Récupérer le dossier parent si fourni
        if ($parentId) {
            $parent = $entityManager->getRepository(Folder::class)->find($parentId);
            if (!$parent) {
                throw $this->createNotFoundException('Dossier parent non trouvé');
            }
        }

        // Créer une nouvelle instance de Folder
        $folder = new Folder();
        // Définir le parent si applicable
        if ($parent) {
            $folder->setParent($parent);
        }
        
        // Créer le formulaire de dossier
        $form = $this->createForm(FolderFormType::class, $folder);
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-générer le slug si vide
            if (!$folder->getSlug()) {
                // slugger->slug() génère un slug à partir du nom, on le convertit en minuscules, remplaçant les espaces par des tirets et supprimant les caractères spéciaux (ex: "Documents Très Importants" devient "documents-tres-importants")
                $folder->setSlug(strtolower($slugger->slug($folder->getName())));
            }

            // Persister le dossier en base de données et flush (exécuter la requête)
            $entityManager->persist($folder);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Dossier "%s" créé avec succès', $folder->getName()));

            // Rediriger vers la liste des dossiers après création.
            return $this->redirectToRoute('app_admin_list_folders');
        }

        // Rendre la vue avec le formulaire de création de dossier
        return $this->render('admin/folders/create.html.twig', [
            'form' => $form,
            'parent' => $parent,
        ]);
    }

    
    #[Route('/folders/{id}/edit', name: 'app_admin_edit_folder')]
    public function editFolder(
        Folder $folder,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(FolderFormType::class, $folder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-générer le slug si vide
            if (!$folder->getSlug()) {
                $folder->setSlug(strtolower($slugger->slug($folder->getName())));
            }

            $entityManager->flush();

            $this->addFlash('success', sprintf('Dossier "%s" modifié avec succès', $folder->getName()));

            return $this->redirectToRoute('app_admin_list_folders');
        }

        return $this->render('admin/folders/edit.html.twig', [
            'form' => $form,
            'folder' => $folder,
        ]);
    }

    #[Route('/folders/{id}/delete', name: 'app_admin_delete_folder', methods: ['POST'])]
    public function deleteFolder(
        Folder $folder,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$folder->getId(), $request->request->get('_token'))) {
            $folderName = $folder->getName();
            $entityManager->remove($folder);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Dossier "%s" supprimé avec succès', $folderName));
        }

        return $this->redirectToRoute('app_admin_list_folders');
    }

    // ============ END FOLDERS MANAGEMENT ============

    // ============ DOCUMENTS MANAGEMENT ============

    #[Route('/documents', name: 'app_admin_list_documents')]
    public function listDocuments(EntityManagerInterface $entityManager, DocumentViewRepository $documentViewRepository): Response
    {
        $documents = $entityManager->getRepository(Document::class)->findBy([], ['position' => 'ASC']);

        return $this->render('admin/documents/list.html.twig', [
            'documents' => $documents,
            'documentViewRepository' => $documentViewRepository,
        ]);
    }

    #[Route('/documents/stats', name: 'app_admin_documents_view_stats')]
    public function documentsViewStats(DocumentViewRepository $documentViewRepository, Request $request): Response
    {
        $startDateStr = $request->query->get('startDate');
        $startTimeStr = $request->query->get('startTime', '00:00');
        $endDateStr = $request->query->get('endDate');
        $endTimeStr = $request->query->get('endTime', '23:59');
        $userQuery = trim((string) $request->query->get('user', ''));

        $sortRaw = (string) $request->query->get('userSort', 'opens');
        $userSort = match ($sortRaw) {
            'docs' => 'docs',
            'last' => 'last',
            default => 'opens',
        };

        $topLimit = (int) $request->query->get('top', 20);
        if ($topLimit < 1) {
            $topLimit = 20;
        }
        if ($topLimit > 100) {
            $topLimit = 100;
        }

        $viewedFrom = null;
        $viewedTo = null;

        if ($startDateStr) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDateStr.' '.$startTimeStr);
            $viewedFrom = $parsed !== false ? $parsed : null;
        }

        if ($endDateStr) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $endDateStr.' '.$endTimeStr);
            $viewedTo = $parsed !== false ? $parsed : null;
        }

        $emailFilter = $userQuery !== '' ? $userQuery : null;

        $summary = $documentViewRepository->getGlobalViewSummary($viewedFrom, $viewedTo, $emailFilter);
        $byUser = $documentViewRepository->aggregateGlobalViewsByUserFiltered(
            $viewedFrom,
            $viewedTo,
            $emailFilter,
            $userSort
        );
        $topDocuments = $documentViewRepository->findTopDocumentsByViewsFiltered(
            $viewedFrom,
            $viewedTo,
            $emailFilter,
            $topLimit
        );

        return $this->render('admin/documents/stats.html.twig', [
            'summary' => $summary,
            'byUser' => $byUser,
            'topDocuments' => $topDocuments,
            'startDate' => $startDateStr,
            'startTime' => $startTimeStr,
            'endDate' => $endDateStr,
            'endTime' => $endTimeStr,
            'userQuery' => $userQuery,
            'userSort' => $userSort,
            'topLimit' => $topLimit,
        ]);
    }

    #[Route('/documents/{id}/views', name: 'app_admin_document_views')]
    public function documentViews(
        Document $document,
        DocumentViewRepository $documentViewRepository,
        Request $request
    ): Response {
        $startDateStr = $request->query->get('startDate');
        $startTimeStr = $request->query->get('startTime', '00:00');
        $endDateStr = $request->query->get('endDate');
        $endTimeStr = $request->query->get('endTime', '23:59');
        $userQuery = trim((string) $request->query->get('user', ''));

        $viewedFrom = null;
        $viewedTo = null;

        if ($startDateStr) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDateStr.' '.$startTimeStr);
            $viewedFrom = $parsed !== false ? $parsed : null;
        }

        if ($endDateStr) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $endDateStr.' '.$endTimeStr);
            $viewedTo = $parsed !== false ? $parsed : null;
        }

        $byUser = $documentViewRepository->aggregateViewsByUserFiltered(
            $document,
            $viewedFrom,
            $viewedTo,
            $userQuery !== '' ? $userQuery : null
        );
        $totalFiltered = $documentViewRepository->countTotalViewsFiltered(
            $document,
            $viewedFrom,
            $viewedTo,
            $userQuery !== '' ? $userQuery : null
        );

        return $this->render('admin/documents/views.html.twig', [
            'document' => $document,
            'byUser' => $byUser,
            'totalFiltered' => $totalFiltered,
            'uniqueUsersFiltered' => \count($byUser),
            'totalAllTime' => $documentViewRepository->countTotalViews($document),
            'uniqueAllTime' => $documentViewRepository->countUniqueViews($document),
            'startDate' => $startDateStr,
            'startTime' => $startTimeStr,
            'endDate' => $endDateStr,
            'endTime' => $endTimeStr,
            'userQuery' => $userQuery,
        ]);
    }

    #[Route('/documents/create', name: 'app_admin_create_document')]
    public function createDocument(
        Request $request,
        EntityManagerInterface $entityManager,
        FileManager $fileManager,
        PdfExtractor $pdfExtractor
    ): Response {
        $document = new Document();
        $form = $this->createForm(DocumentFormType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();

            // Gérer le type de document
            if ($document->getDocumentType() === Document::TYPE_FILE && $uploadedFile) {
                // Get MIME type before moving the file
                $mimeType = $uploadedFile->getMimeType();
                // Upload du fichier
                $relativePath = $fileManager->uploadFile($uploadedFile, 'documents');
                $document->setPath($relativePath);
                $document->setMimeType($mimeType);

                // Extraire le texte si c'est un PDF
                if ($document->isPdf()) {
                    $fullPath = $fileManager->getFullPath($relativePath);
                    $textContent = $pdfExtractor->extractTextFromPdf($fullPath);
                    if ($textContent) {
                        $document->setTextContent($textContent);
                    }
                }
            } elseif ($document->getDocumentType() === Document::TYPE_LINK && $document->getExternalUrl()) {
                $document->setPath($document->getExternalUrl());
            } else {
                $this->addFlash('error', 'Veuillez télécharger un fichier ou entrer une URL');
                return $this->render('admin/documents/create.html.twig', [
                    'form' => $form,
                ]);
            }

            $entityManager->persist($document);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Document "%s" créé avec succès', $document->getTitle()));

            return $this->redirectToRoute('app_admin_list_documents');
        }

        return $this->render('admin/documents/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/documents/{id}/edit', name: 'app_admin_edit_document')]
    public function editDocument(
        Document $document,
        Request $request,
        EntityManagerInterface $entityManager,
        FileManager $fileManager,
        PdfExtractor $pdfExtractor
    ): Response {
        $currentPath = $document->getPath();
        $form = $this->createForm(DocumentFormType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();

            if ($uploadedFile) {
                // Get MIME type before moving the file
                $mimeType = $uploadedFile->getMimeType();
                // Supprimer l'ancien fichier s'il existe
                if ($currentPath && $document->getDocumentType() === Document::TYPE_FILE) {
                    $fileManager->deleteFile($currentPath);
                }

                // Upload du nouveau fichier
                $relativePath = $fileManager->uploadFile($uploadedFile, 'documents');
                $document->setPath($relativePath);
                $document->setMimeType($mimeType);

                // Extraire le texte si c'est un PDF
                if ($document->isPdf()) {
                    $fullPath = $fileManager->getFullPath($relativePath);
                    $textContent = $pdfExtractor->extractTextFromPdf($fullPath);
                    if ($textContent) {
                        $document->setTextContent($textContent);
                    }
                }
            } elseif ($document->getDocumentType() === Document::TYPE_LINK && $document->getExternalUrl()) {
                $document->setPath($document->getExternalUrl());
            }

            $entityManager->flush();

            $this->addFlash('success', sprintf('Document "%s" modifié avec succès', $document->getTitle()));

            return $this->redirectToRoute('app_admin_list_documents');
        }

        return $this->render('admin/documents/edit.html.twig', [
            'form' => $form,
            'document' => $document,
        ]);
    }

    #[Route('/documents/{id}/delete', name: 'app_admin_delete_document', methods: ['POST'])]
    public function deleteDocument(
        Document $document,
        Request $request,
        EntityManagerInterface $entityManager,
        FileManager $fileManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $documentTitle = $document->getTitle();

            // Supprimer le fichier physique si c'est un fichier
            if ($document->getDocumentType() === Document::TYPE_FILE && $document->getPath()) {
                $fileManager->deleteFile($document->getPath());
            }

            $entityManager->remove($document);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Document "%s" supprimé avec succès', $documentTitle));
        }

        return $this->redirectToRoute('app_admin_list_documents');
    }

    // ============ END DOCUMENTS MANAGEMENT ============

    // ============ COMMENTS MANAGEMENT ============

    #[Route('/comments', name: 'app_admin_list_comments')]
    public function listComments(CommentRepository $commentRepository, Request $request): Response
    {
        $startDate = null;
        $endDate = null;
        $orderByRaw = (string) $request->query->get('orderBy', 'DESC');
        $orderBy = strtoupper($orderByRaw) === 'ASC' ? 'ASC' : 'DESC';

        // Récupérer les paramètres de filtre depuis la requête
        $startDateStr = $request->query->get('startDate');
        $startTimeStr = $request->query->get('startTime', '00:00');
        $endDateStr = $request->query->get('endDate');
        $endTimeStr = $request->query->get('endTime', '23:59');

        // createFromFormat ne lance pas d'exception : il retourne false si la date est invalide
        if ($startDateStr) {
            $parsed = \DateTime::createFromFormat('Y-m-d H:i', $startDateStr.' '.$startTimeStr);
            $startDate = $parsed !== false ? $parsed : null;
        }

        if ($endDateStr) {
            $parsed = \DateTime::createFromFormat('Y-m-d H:i', $endDateStr.' '.$endTimeStr);
            $endDate = $parsed !== false ? $parsed : null;
        }

        $comments = $commentRepository->findByDateRange($startDate, $endDate, $orderBy);

        return $this->render('admin/comments/list.html.twig', [
            'comments' => $comments,
            'startDate' => $startDateStr,
            'startTime' => $startTimeStr,
            'endDate' => $endDateStr,
            'endTime' => $endTimeStr,
            'orderBy' => $orderBy,
        ]);
    }

    #[Route('/comments/{id}/delete', name: 'app_admin_delete_comment', methods: ['POST'])]
    public function deleteComment(
        \App\Entity\Comment $comment,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$comment->getId(), $request->request->get('_token'))) {
            $email = $comment->getUser()->getEmail();
            $documentTitle = $comment->getDocument()->getTitle();
            
            $entityManager->remove($comment);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Commentaire de %s sur "%s" supprimé avec succès', $email, $documentTitle));
        }

        return $this->redirectToRoute('app_admin_list_comments');
    }

    // ============ END COMMENTS MANAGEMENT ============

    /** Chaîne « Ancêtre / … / Dossier » pour les listes déroulantes d'admin. */
    private function buildFolderAdminLabel(Folder $folder): string
    {
        $parts = [];
        for ($node = $folder; $node !== null; $node = $node->getParent()) {
            array_unshift($parts, $node->getName());
        }

        return implode(' / ', $parts);
    }
}
