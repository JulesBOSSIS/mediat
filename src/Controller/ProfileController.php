<?php

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Repository\CommentRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile')]
    public function index(CommentRepository $commentRepository, DocumentRepository $documentRepository): Response
    {
        $user = $this->getUser();

        return $this->render('profile/index.html.twig', [
            'comments' => $commentRepository->findAllCommentsByUserForProfile($user),
            'favoriteDocuments' => $documentRepository->findFavoritesByUser($user),
            'unreadRootCommentIds' => $commentRepository->findIdsOfRootCommentsWithUnreadReplies($user),
        ]);
    }

    #[Route('/comments', name: 'app_profile_comments')]
    public function comments(CommentRepository $commentRepository): Response
    {
        $user = $this->getUser();
        $comments = $commentRepository->findAllCommentsByUserForProfile($user);

        return $this->render('profile/comments.html.twig', [
            'comments' => $comments,
            'unreadRootCommentIds' => $commentRepository->findIdsOfRootCommentsWithUnreadReplies($user),
        ]);
    }

    #[Route('/change-password', name: 'app_profile_change_password')]
    #[IsGranted('ROLE_ADMIN')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('plainPassword')->getData();

            // Vérifier que le mot de passe actuel est correct
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            // Hasher et définir le nouveau mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            // Sauvegarder l'utilisateur
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}
