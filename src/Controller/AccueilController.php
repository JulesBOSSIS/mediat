<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccueilController extends AbstractController
{
    // Affiche la page d'accueil avec les dossiers racine accessibles et les statistiques
    #[Route('/accueil', name: 'app_accueil')]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer l'utilisateur actuel
        $user = $this->getUser();
        
        // Récupérer tous les dossiers racine
        $allRootFolders = $entityManager->getRepository(Folder::class)->findBy(
            ['parent' => null],
            ['position' => 'ASC', 'name' => 'ASC']
        );

        // Filtrer les dossiers accessibles à l'utilisateur
        $rootFolders = array_filter($allRootFolders, function (Folder $folder) use ($user) {
            return $folder->isAccessibleBy($user);
        });

        // Compter les documents et dossiers accessibles à l'utilisateur
        $totalDocuments = $this->countAccessibleDocuments($rootFolders, $user);
        $totalFolders = $this->countAccessibleFolders($rootFolders, $user);

        // Envoyer les données (dossiers racine et statistiques) à la vue
        return $this->render('accueil/index.html.twig', [
            'rootFolders' => $rootFolders,
            'totalDocuments' => $totalDocuments,
            'totalFolders' => $totalFolders,
        ]);
    }

    // Affiche un dossier en lecture seule avec tous ses documents et sous-dossiers
    #[Route('/folder/{id}/view', name: 'app_folder_view')]
    #[IsGranted('ROLE_USER')]
    public function viewFolder(Folder $folder, EntityManagerInterface $entityManager): Response
    {
        // Récupérer l'utilisateur actuel
        $user = $this->getUser();
        
        // Vérifier si l'utilisateur a accès à ce dossier
        if (!$folder->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce dossier.');
        }

        // Récupérer les documents du dossier triés par position
        $documents = $folder->getDocuments();

        // Récupérer les sous-dossiers accessibles triés par position
        $allChildren = $folder->getChildren();
        $children = array_filter($allChildren->toArray(), function (Folder $child) use ($user) {
            return $child->isAccessibleBy($user);
        });

        // Récupérer les ancêtres pour le fil d'Ariane
        $ancestors = [];
        $currentFolder = $folder->getParent();
        while ($currentFolder !== null) {
            array_unshift($ancestors, $currentFolder);
            $currentFolder = $currentFolder->getParent();
        }

        // Envoyer les données à la vue
        return $this->render('folder/view.html.twig', [
            'folder' => $folder,
            'documents' => $documents,
            'children' => $children,
            'ancestors' => $ancestors,
        ]);
    }

    /**
     * Compte récursivement tous les dossiers accessibles à l'utilisateur
     */
    private function countAccessibleFolders(array $folders, UserInterface $user): int
    {
        $count = 0;
        
        foreach ($folders as $folder) {
            // Si le dossier est accessible, l'ajouter au compteur
            if ($folder->isAccessibleBy($user)) {
                $count++;
                
                // Compter récursivement les sous-dossiers accessibles
                $children = $folder->getChildren()->toArray();
                $accessibleChildren = array_filter($children, function (Folder $child) use ($user) {
                    return $child->isAccessibleBy($user);
                });
                
                if (!empty($accessibleChildren)) {
                    $count += $this->countAccessibleFolders($accessibleChildren, $user);
                }
            }
        }
        
        return $count;
    }

    /**
     * Compte récursivement tous les documents accessibles à l'utilisateur
     */
    private function countAccessibleDocuments(array $folders, UserInterface $user): int
    {
        $count = 0;
        
        foreach ($folders as $folder) {
            // Si le dossier est accessible, compter ses documents
            if ($folder->isAccessibleBy($user)) {
                $count += $folder->getDocuments()->count();
                
                // Compter récursivement les documents des sous-dossiers accessibles
                $children = $folder->getChildren()->toArray();
                $accessibleChildren = array_filter($children, function (Folder $child) use ($user) {
                    return $child->isAccessibleBy($user);
                });
                
                if (!empty($accessibleChildren)) {
                    $count += $this->countAccessibleDocuments($accessibleChildren, $user);
                }
            }
        }
        
        return $count;
    }
}
