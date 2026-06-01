<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Rating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    /**
     * Trouver la note d'un utilisateur pour un document
     */
    public function findUserRating(User $user, Document $document): ?Rating
    {
        return $this->findOneBy([
            'user' => $user,
            'document' => $document,
        ]);
    }

    /**
     * Obtenir la moyenne des notes pour un document
     */
    public function getAverageRating(Document $document): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.score) as avg_rating, COUNT(r.id) as total_ratings')
            ->where('r.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['avg_rating'] ? (float) $result['avg_rating'] : 0;
    }

    /**
     * Obtenir le nombre de votes pour un document
     */
    public function getTotalRatings(Document $document): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtenir la distribution des notes pour un document
     */
    public function getRatingDistribution(Document $document): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.score, COUNT(r.id) as count')
            ->where('r.document = :document')
            ->setParameter('document', $document)
            ->groupBy('r.score')
            ->orderBy('r.score', 'DESC')
            ->getQuery()
            ->getResult();

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0, 0 => 0];
        foreach ($results as $result) {
            $distribution[$result['score']] = (int) $result['count'];
        }

        return $distribution;
    }
}
