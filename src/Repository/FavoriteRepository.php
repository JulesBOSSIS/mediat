<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * Vérifie si un document est en favoris pour un utilisateur
     */
    public function isFavorite(User $user, Document $document): bool
    {
        return $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->andWhere('f.document = :document')
            ->setParameter('user', $user)
            ->setParameter('document', $document)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /**
     * Trouve un favoris par user et document
     */
    public function findFavorite(User $user, Document $document): ?Favorite
    {
        return $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->andWhere('f.document = :document')
            ->setParameter('user', $user)
            ->setParameter('document', $document)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
