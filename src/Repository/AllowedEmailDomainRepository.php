<?php

namespace App\Repository;

use App\Entity\AllowedEmailDomain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AllowedEmailDomain>
 */
class AllowedEmailDomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AllowedEmailDomain::class);
    }

    /**
     * Find an active domain by domain name
     */
    public function findActiveByDomain(string $domain): ?AllowedEmailDomain
    {
        return $this->createQueryBuilder('a')
            ->where('a.domain = :domain')
            ->andWhere('a.isActive = :active')
            ->setParameter('domain', strtolower(trim($domain)))
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if a domain is allowed
     */
    public function isDomainAllowed(string $domain): bool
    {
        return $this->findActiveByDomain($domain) !== null;
    }

    /**
     * Find all active domains
     */
    public function findAllActive(): array
    {
        return $this->findBy(['isActive' => true], ['domain' => 'ASC']);
    }
}
