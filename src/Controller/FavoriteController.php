<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Favorite;
use App\Repository\DocumentRepository;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/favorites')]
final class FavoriteController extends AbstractController
{
    #[Route('', name: 'app_favorites')]
    public function index(DocumentRepository $documentRepository): Response
    {
        $user = $this->getUser();
        
        // Récupérer les documents favoris de l'utilisateur
        $favoriteDocuments = $documentRepository->findFavoritesByUser($user);
        
        return $this->render('favorite/index.html.twig', [
            'favoriteDocuments' => $favoriteDocuments,
        ]);
    }

    #[Route('/add/{id}', name: 'app_favorite_add', methods: ['POST'])]
    public function add(
        Document $document,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();

        // Vérifier si le document est déjà en favoris
        if ($favoriteRepository->isFavorite($user, $document)) {
            return new JsonResponse(['message' => 'Déjà en favoris'], Response::HTTP_CONFLICT);
        }

        // Créer un nouveau favoris
        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setDocument($document);

        $entityManager->persist($favorite);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Ajouté aux favoris'], Response::HTTP_CREATED);
    }

    #[Route('/remove/{id}', name: 'app_favorite_remove', methods: ['POST', 'DELETE'])]
    public function remove(
        Document $document,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();

        $favorite = $favoriteRepository->findFavorite($user, $document);

        if (!$favorite) {
            return new JsonResponse(['message' => 'Pas en favoris'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($favorite);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Retiré des favoris'], Response::HTTP_OK);
    }
}
