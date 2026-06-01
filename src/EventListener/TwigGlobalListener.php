<?php

namespace App\EventListener;

use App\Entity\Folder;
use App\Entity\User;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class TwigGlobalListener implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private CommentRepository $commentRepository,
        private bool $demoMode = false,
        private string $demoUserEmail = '',
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    private const PUBLIC_ROUTES_WITHOUT_SIDEBAR = [
        'app_login',
        'app_register',
        'app_verify_code',
        'app_forgot_password',
        'app_reset_password',
        'app_demo_login',
    ];

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (\is_string($route) && \in_array($route, self::PUBLIC_ROUTES_WITHOUT_SIDEBAR, true)) {
            $this->setTwigGlobalsWithoutSidebar();

            return;
        }

        // Récupérer tous les dossiers racines
        $allRootFolders = $this->entityManager->getRepository(Folder::class)->findBy(
            ['parent' => null],
            ['position' => 'ASC', 'name' => 'ASC']
        );

        // Récupérer l'utilisateur connecté
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // Filtrer les dossiers accessibles à l'utilisateur
        $rootFolders = array_filter($allRootFolders, function (Folder $folder) use ($user) {
            if ($user === null) {
                // Si pas d'utilisateur connecté, seulement les dossiers publics
                return $folder->getRequiredRoles() === null || empty($folder->getRequiredRoles());
            }
            return $folder->isAccessibleBy($user);
        });

        $this->twig->addGlobal('rootFolders', $rootFolders);

        $unreadCommentReplyCount = 0;
        if ($user instanceof User) {
            $unreadCommentReplyCount = $this->commentRepository->countUnreadReplyNotificationsForUser($user);
        }
        $this->twig->addGlobal('unread_comment_reply_count', $unreadCommentReplyCount);
        $this->setDemoTwigGlobals($user);
    }

    private function setTwigGlobalsWithoutSidebar(): void
    {
        $this->twig->addGlobal('rootFolders', []);
        $this->twig->addGlobal('unread_comment_reply_count', 0);
        $this->setDemoTwigGlobals(null);
    }

    private function setDemoTwigGlobals(mixed $user): void
    {
        $isDemoSession = $this->demoMode
            && $user instanceof User
            && $user->getEmail() === $this->demoUserEmail;
        $this->twig->addGlobal('demo_mode', $this->demoMode);
        $this->twig->addGlobal('is_demo_session', $isDemoSession);
    }
}
