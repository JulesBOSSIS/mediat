<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Identifiants des comptes dont le JSON `roles` contient ROLE_ADMIN (hors DQL : pas de CAST / LIKE JSON côté ORM).
     *
     * @return list<int>
     */
    public function findAdminRoleUserIds(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();
        $quotedTable = $conn->quoteSingleIdentifier($table);
        $platform = $conn->getDatabasePlatform();
        $pattern = '%'.User::ROLE_ADMIN.'%';

        if ($platform instanceof PostgreSQLPlatform) {
            $sql = "SELECT id FROM {$quotedTable} WHERE roles::text LIKE ?";
        } elseif ($platform instanceof SQLitePlatform) {
            $sql = "SELECT id FROM {$quotedTable} WHERE CAST(roles AS TEXT) LIKE ?";
        } else {
            $sql = "SELECT id FROM {$quotedTable} WHERE CAST(roles AS CHAR) LIKE ?";
        }

        $rows = $conn->fetchFirstColumn($sql, [$pattern], [ParameterType::STRING]);

        return array_map(static fn (mixed $id): int => (int) $id, $rows);
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
