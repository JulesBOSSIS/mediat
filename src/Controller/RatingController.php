<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Rating;
use App\Repository\RatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ratings')]
#[IsGranted('ROLE_USER')]
class RatingController extends AbstractController
{
    #[Route('/rate/{id}', name: 'app_rating_rate', methods: ['POST'])]
    public function rate(
        Document $document,
        Request $request,
        EntityManagerInterface $entityManager,
        RatingRepository $ratingRepository
    ): JsonResponse {
        $score = (int) $request->request->get('score');

        // Valider le score
        if ($score < 0 || $score > 5) {
            return new JsonResponse(['error' => 'La note doit être entre 0 et 5'], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Chercher une note existante
        $rating = $ratingRepository->findUserRating($user, $document);

        if ($rating) {
            // Mettre à jour la note existante
            $rating->setScore($score);
            $rating->setUpdatedAt(new \DateTime());
        } else {
            // Créer une nouvelle note
            $rating = new Rating();
            $rating->setUser($user);
            $rating->setDocument($document);
            $rating->setScore($score);
            $entityManager->persist($rating);
        }

        $entityManager->flush();

        // Retourner la moyenne et le nombre de votes
        $average = $ratingRepository->getAverageRating($document);
        $total = $ratingRepository->getTotalRatings($document);

        return new JsonResponse([
            'success' => true,
            'average' => round($average, 2),
            'total' => $total,
            'score' => $score,
        ]);
    }

    #[Route('/document/{id}', name: 'app_rating_get', methods: ['GET'])]
    public function getDocumentRatings(
        Document $document,
        RatingRepository $ratingRepository
    ): JsonResponse {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        $userRating = null;

        if ($user) {
            $rating = $ratingRepository->findUserRating($user, $document);
            $userRating = $rating?->getScore();
        }

        $average = $ratingRepository->getAverageRating($document);
        $total = $ratingRepository->getTotalRatings($document);
        $distribution = $ratingRepository->getRatingDistribution($document);

        return new JsonResponse([
            'average' => round($average, 2),
            'total' => $total,
            'userRating' => $userRating,
            'distribution' => $distribution,
        ]);
    }
}
