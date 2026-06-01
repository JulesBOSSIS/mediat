<?php

namespace App\Repository;

use App\Entity\RegistrationRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegistrationRequest>
 */
class RegistrationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistrationRequest::class);
    }

    public function findByEmail(string $email): ?RegistrationRequest
    {
        return $this->findOneBy(['email' => $email]);
    }
}
