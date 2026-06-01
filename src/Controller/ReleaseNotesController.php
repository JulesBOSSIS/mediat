<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ReleaseNotesController extends AbstractController
{
    public function __construct(
        #[Autowire('%app.version%')]
        private readonly string $appVersion,
    ) {
    }

    #[Route('/notes-de-version', name: 'app_release_notes')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $releases = self::releaseNotes();

        if ($this->getParameter('kernel.debug')) {
            $first = $releases[0]['version'] ?? '';
            if ($first !== $this->appVersion) {
                throw new \LogicException(sprintf(
                    'Incohérence : app.version vaut "%s" mais la première entrée de releaseNotes() est "%s". Alignez config/services.yaml (paramètre app.version) et la première version dans releaseNotes().',
                    $this->appVersion,
                    $first
                ));
            }
        }

        return $this->render('release_notes/index.html.twig', [
            'releases' => $releases,
            'currentVersion' => $this->appVersion,
        ]);
    }

    /**
     * @return list<array{version: string, releasedAt: \DateTimeImmutable, items: list<string>}>
     */
    public static function releaseNotes(): array
    {
        return [
            [
                'version' => '1.3.0',
                'releasedAt' => new \DateTimeImmutable('2026-05-11'),
                'items' => [
                    'Ajustements des boutons sur la page de profil.',
                    'Passage sur la branche LTS de Symfony.',
                ],
            ],
            [
                'version' => '1.2.1',
                'releasedAt' => new \DateTimeImmutable('2026-05-04'),
                'items' => [
                    'Amélioration de l’interface utilisateur.',
                ],
            ],
            [
                'version' => '1.2.0',
                'releasedAt' => new \DateTimeImmutable('2026-04-27'),
                'items' => [
                    'Amélioration de la barre de recherche et de son fonctionnement.',
                ],
            ],
            [
                'version' => '1.1.0',
                'releasedAt' => new \DateTimeImmutable('2026-02-18'),
                'items' => [
                    'Refonte UX et UI sur la majorité du site.',
                ],
            ],
            [
                'version' => '1.0.0',
                'releasedAt' => new \DateTimeImmutable('2026-01-23'),
                'items' => [
                    'Première version : sortie du logiciel MediaT.',
                ],
            ],
        ];
    }
}
