<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Document;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Obtenir tous les commentaires racines pour un document (ancien comportement ORM :
     * un seul niveau de réponses chargé — préférer findAllCommentsForDocumentOrdered + arbre côté API).
     */
    public function findByDocument(Document $document)
    {
        return $this->createQueryBuilder('c')
            ->where('c.document = :document')
            ->andWhere('c.parentComment IS NULL')
            ->setParameter('document', $document)
            ->leftJoin('c.replies', 'r')
            ->addSelect('r')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les commentaires d'un document, ordre chronologique (construction d'arbre côté contrôleur).
     *
     * @return Comment[]
     */
    public function findAllCommentsForDocumentOrdered(Document $document): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.document = :document')
            ->setParameter('document', $document)
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les commentaires d'un utilisateur
     */
    public function findByUser(User $user)
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->leftJoin('c.document', 'd')
            ->addSelect('d')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Commentaires racines de l'utilisateur (fil de discussion qu'il a ouverts), avec le document.
     */
    public function findRootCommentsByUserForProfile(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.parentComment IS NULL')
            ->setParameter('user', $user)
            ->leftJoin('c.document', 'd')
            ->addSelect('d')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les messages de l'utilisateur (commentaires racine et réponses), pour le profil.
     *
     * @return Comment[]
     */
    public function findAllCommentsByUserForProfile(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->leftJoin('c.document', 'd')
            ->addSelect('d')
            ->leftJoin('c.parentComment', 'p')
            ->addSelect('p')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque comme vues les réponses sur les fils racines de l'utilisateur pour ce document.
     */
    public function markRootCommentRepliesAsReadForUserOnDocument(User $user, Document $document): void
    {
        $now = new \DateTimeImmutable();

        $this->createQueryBuilder('c')
            ->update()
            ->set('c.lastReplyReadAt', ':now')
            ->where('c.document = :document')
            ->andWhere('c.user = :user')
            ->andWhere('c.parentComment IS NULL')
            ->setParameter('now', $now)
            ->setParameter('document', $document)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Nombre de fils (commentaires racines) ayant au moins une réponse d'un autre utilisateur non lue.
     */
    public function countUnreadReplyNotificationsForUser(User $user): int
    {
        $subQb = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Comment::class, 'r')
            ->innerJoin('r.user', 'ru')
            ->where('r.threadRoot = c')
            ->andWhere('ru != c.user')
            ->andWhere('(c.lastReplyReadAt IS NULL OR r.createdAt > c.lastReplyReadAt)');

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.user = :user')
            ->andWhere('c.parentComment IS NULL')
            ->andWhere($this->getEntityManager()->getExpressionBuilder()->exists($subQb->getDQL()))
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Identifiants des commentaires racines avec réponse d'un tiers non lue (pour badges liste profil).
     *
     * @return int[]
     */
    public function findIdsOfRootCommentsWithUnreadReplies(User $user): array
    {
        $subQb = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Comment::class, 'r')
            ->innerJoin('r.user', 'ru')
            ->where('r.threadRoot = c')
            ->andWhere('ru != c.user')
            ->andWhere('(c.lastReplyReadAt IS NULL OR r.createdAt > c.lastReplyReadAt)');

        $rows = $this->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.user = :user')
            ->andWhere('c.parentComment IS NULL')
            ->andWhere($this->getEntityManager()->getExpressionBuilder()->exists($subQb->getDQL()))
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * Compter les commentaires pour un document
     */
    public function countByDocument(Document $document): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Filtrer les commentaires par plage de dates
     */
    public function findByDateRange(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null, string $orderBy = 'DESC'): array
    {
        $direction = strtoupper($orderBy) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->leftJoin('c.document', 'd')
            ->addSelect('d');

        if ($startDate !== null) {
            $qb->andWhere('c.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('c.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $qb->orderBy('c.createdAt', $direction);

        return $qb->getQuery()->getResult();
    }
}
