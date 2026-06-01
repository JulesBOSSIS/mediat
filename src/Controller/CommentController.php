<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Document;
use App\Entity\User;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comments')]
#[IsGranted('ROLE_USER')]
class CommentController extends AbstractController
{
    #[Route('/add/{id}', name: 'app_comment_add', methods: ['POST'])]
    public function add(
        Document $document,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $content = trim($request->request->get('content', ''));

        // Valider le contenu
        if (empty($content)) {
            return new JsonResponse(['error' => 'Le commentaire ne peut pas être vide'], 400);
        }

        if (strlen($content) > 5000) {
            return new JsonResponse(['error' => 'Le commentaire ne peut pas dépasser 5000 caractères'], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Créer le commentaire
        $comment = new Comment();
        $comment->setUser($user);
        $comment->setDocument($document);
        $comment->setContent($content);

        $entityManager->persist($comment);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Commentaire ajouté avec succès',
            'commentId' => $comment->getId(),
        ]);
    }

    #[Route('/delete/{id}', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(
        Comment $comment,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Vérifier que l'utilisateur est propriétaire du commentaire ou admin
        if ($comment->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $entityManager->remove($comment);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/document/{id}', name: 'app_comment_get', methods: ['GET'])]
    public function getComments(
        Document $document,
        CommentRepository $commentRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $commentRepository->markRootCommentRepliesAsReadForUserOnDocument($user, $document);

        $flat = $commentRepository->findAllCommentsForDocumentOrdered($document);
        $byParent = [];
        foreach ($flat as $comment) {
            $key = $comment->getParentComment() !== null ? (string) $comment->getParentComment()->getId() : 'root';
            if (!isset($byParent[$key])) {
                $byParent[$key] = [];
            }
            $byParent[$key][] = $comment;
        }
        foreach ($byParent as &$list) {
            usort($list, static fn (Comment $a, Comment $b) => $a->getCreatedAt() <=> $b->getCreatedAt());
        }
        unset($list);

        $roots = $byParent['root'] ?? [];
        usort($roots, static fn (Comment $a, Comment $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        $commentsJson = [];
        foreach ($roots as $root) {
            $commentsJson[] = $this->serializeCommentBranch($root, $byParent, $user);
        }

        return new JsonResponse([
            'comments' => $commentsJson,
            'totalCommentCount' => $commentRepository->countByDocument($document),
        ]);
    }

    /**
     * @param array<string, Comment[]> $byParent
     */
    private function serializeCommentBranch(Comment $comment, array $byParent, User $viewer): array
    {
        $id = (string) $comment->getId();
        $children = $byParent[$id] ?? [];

        $replies = [];
        foreach ($children as $child) {
            $replies[] = $this->serializeCommentBranch($child, $byParent, $viewer);
        }

        return [
            'id' => $comment->getId(),
            'author' => $comment->getUser()?->getEmail() ?? '',
            'content' => $comment->getContent() ?? '',
            'createdAt' => $comment->getCreatedAt()?->format('d/m/Y H:i') ?? '',
            'isOwner' => $comment->getUser() === $viewer,
            'replies' => $replies,
        ];
    }

    #[Route('/reply/{id}', name: 'app_comment_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function replyToComment(
        Comment $comment,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $content = trim($request->request->get('content', ''));

        // Valider le contenu
        if (empty($content)) {
            return new JsonResponse(['error' => 'La réponse ne peut pas être vide'], 400);
        }

        if (strlen($content) > 5000) {
            return new JsonResponse(['error' => 'La réponse ne peut pas dépasser 5000 caractères'], 400);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Créer la réponse (qui est aussi un commentaire)
        $reply = new Comment();
        $reply->setUser($user);
        $reply->setDocument($comment->getDocument());
        $reply->setContent($content);
        $reply->setParentComment($comment);
        $reply->setThreadRoot($comment->getRootOfThread());

        $entityManager->persist($reply);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Réponse ajoutée avec succès',
            'replyId' => $reply->getId(),
        ]);
    }
}
