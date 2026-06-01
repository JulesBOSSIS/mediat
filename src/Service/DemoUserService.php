<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $demoUserEmail,
    ) {
    }

    /**
     * Compte démo avec uniquement ROLE_USER (créé automatiquement s'il n'existe pas).
     */
    public function getOrCreateDemoUser(): User
    {
        $user = $this->userRepository->findOneBy(['email' => $this->demoUserEmail]);

        if ($user !== null) {
            if (\in_array(User::ROLE_ADMIN, $user->getRoles(), true)
                || \in_array(User::ROLE_PARTNER, $user->getRoles(), true)) {
                $user->setRoles([]);
            }
            if (!$user->isActive()) {
                $user->setIsActive(true);
                $this->entityManager->flush();
            }

            return $user;
        }

        $user = new User();
        $user->setEmail($this->demoUserEmail);
        $user->setRoles([]);
        $user->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
