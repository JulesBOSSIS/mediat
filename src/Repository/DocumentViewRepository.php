<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentView;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentView>
 */
class DocumentViewRepository extends ServiceEntityRepository
{
    // Délai en secondes pour éviter les comptes multiples rapides (5 minutes par défaut)
    private const VIEW_DEBOUNCE_SECONDS = 300;

    /** @var list<int>|null */
    private ?array $adminUserIdsCache = null;

    public function __construct(ManagerRegistry $registry, private EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, DocumentView::class);
    }

    /**
     * @return list<int>
     */
    private function getAdminUserIds(): array
    {
        if ($this->adminUserIdsCache !== null) {
            return $this->adminUserIdsCache;
        }

        $this->adminUserIdsCache = $this->entityManager->getRepository(User::class)->findAdminRoleUserIds();

        return $this->adminUserIdsCache;
    }

    /**
     * Exclut les comptes ROLE_ADMIN (liste d'IDs via SQL natif, sans CAST en DQL).
     */
    private function applyExcludeAdminUsers(QueryBuilder $qb, string $userAlias = 'u'): void
    {
        $ids = $this->getAdminUserIds();
        if ($ids === []) {
            return;
        }

        $qb->andWhere($qb->expr()->notIn($userAlias.'.id', ':adminUserIds'))
            ->setParameter('adminUserIds', $ids, ArrayParameterType::INTEGER);
    }

    /**
     * Enregistre une visualisation si elle n'a pas été enregistrée récemment
     * 
     * @param Document $document Le document visualisé
     * @param User $user L'utilisateur qui visualise
     * @return bool true si enregistrée, false si ignorée (déjà vue récemment)
     */
    public function recordViewIfNeeded(Document $document, User $user): bool
    {
        $lastView = $this->findLastView($document, $user);
        $now = new \DateTimeImmutable();

        // Si pas de vue précédente, enregistrer
        if (!$lastView) {
            $view = new DocumentView();
            $view->setDocument($document);
            $view->setUser($user);
            $view->setViewedAt($now);
            
            $this->entityManager->persist($view);
            $this->entityManager->flush();
            
            return true;
        }

        // Si la dernière vue est trop récente, ignorer
        $interval = $now->getTimestamp() - $lastView->getViewedAt()->getTimestamp();
        if ($interval < self::VIEW_DEBOUNCE_SECONDS) {
            return false;
        }

        // Enregistrer une nouvelle vue
        $view = new DocumentView();
        $view->setDocument($document);
        $view->setUser($user);
        $view->setViewedAt($now);
        
        $this->entityManager->persist($view);
        $this->entityManager->flush();
        
        return true;
    }

    /**
     * Trouve la dernière visualisation d'un document par un utilisateur
     */
    private function findLastView(Document $document, User $user): ?DocumentView
    {
        return $this->createQueryBuilder('dv')
            ->andWhere('dv.document = :document')
            ->andWhere('dv.user = :user')
            ->setParameter('document', $document)
            ->setParameter('user', $user)
            ->orderBy('dv.viewedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre total de visualisations uniques d'un document
     * (une par utilisateur, basée sur la première vue)
     */
    public function countUniqueViews(Document $document): int
    {
        $qb = $this->createQueryBuilder('dv')
            ->select('COUNT(DISTINCT dv.user)')
            ->innerJoin('dv.user', 'u')
            ->andWhere('dv.document = :document')
            ->setParameter('document', $document);
        $this->applyExcludeAdminUsers($qb, 'u');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte le nombre total de visualisations d'un document
     */
    public function countTotalViews(Document $document): int
    {
        $qb = $this->createQueryBuilder('dv')
            ->select('COUNT(dv)')
            ->innerJoin('dv.user', 'u')
            ->andWhere('dv.document = :document')
            ->setParameter('document', $document);
        $this->applyExcludeAdminUsers($qb, 'u');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère tous les enregistrements de visualisation pour un document
     */
    public function findByDocument(Document $document): array
    {
        return $this->createQueryBuilder('dv')
            ->andWhere('dv.document = :document')
            ->setParameter('document', $document)
            ->orderBy('dv.viewedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques agrégées par utilisateur (nombre d'ouvertures enregistrées, première / dernière vue).
     *
     * @return list<array{userId: int, email: string, viewCount: int, firstViewedAt: \DateTimeImmutable, lastViewedAt: \DateTimeImmutable}>
     */
    public function aggregateViewsByUserFiltered(
        Document $document,
        ?\DateTimeImmutable $viewedFrom,
        ?\DateTimeImmutable $viewedTo,
        ?string $emailContains
    ): array {
        $qb = $this->createQueryBuilder('dv')
            ->innerJoin('dv.user', 'u')
            ->select('u.id AS userId')
            ->addSelect('u.email AS email')
            ->addSelect('COUNT(dv.id) AS viewCount')
            ->addSelect('MIN(dv.viewedAt) AS firstViewedAt')
            ->addSelect('MAX(dv.viewedAt) AS lastViewedAt')
            ->andWhere('dv.document = :document')
            ->setParameter('document', $document)
            ->groupBy('u.id')
            ->addGroupBy('u.email')
            ->orderBy('viewCount', 'DESC')
            ->addOrderBy('lastViewedAt', 'DESC');

        if ($viewedFrom !== null) {
            $qb->andWhere('dv.viewedAt >= :from')
                ->setParameter('from', $viewedFrom);
        }
        if ($viewedTo !== null) {
            $qb->andWhere('dv.viewedAt <= :to')
                ->setParameter('to', $viewedTo);
        }
        if ($emailContains !== null && '' !== trim($emailContains)) {
            $qb->andWhere('LOWER(u.email) LIKE :email')
                ->setParameter('email', '%'.strtolower(trim($emailContains)).'%');
        }

        $this->applyExcludeAdminUsers($qb, 'u');

        $rows = $qb->getQuery()->getScalarResult();
        $out = [];
        foreach ($rows as $row) {
            if (!isset($row['userId'], $row['email'], $row['viewCount'], $row['firstViewedAt'], $row['lastViewedAt'])) {
                $v = array_values($row);
                if (\count($v) < 5) {
                    continue;
                }
                $row = [
                    'userId' => $v[0],
                    'email' => $v[1],
                    'viewCount' => $v[2],
                    'firstViewedAt' => $v[3],
                    'lastViewedAt' => $v[4],
                ];
            }

            $first = $row['firstViewedAt'];
            $last = $row['lastViewedAt'];
            if ($first instanceof \DateTimeInterface) {
                $first = \DateTimeImmutable::createFromInterface($first);
            } else {
                $first = new \DateTimeImmutable((string) $first);
            }
            if ($last instanceof \DateTimeInterface) {
                $last = \DateTimeImmutable::createFromInterface($last);
            } else {
                $last = new \DateTimeImmutable((string) $last);
            }

            $out[] = [
                'userId' => (int) $row['userId'],
                'email' => (string) $row['email'],
                'viewCount' => (int) $row['viewCount'],
                'firstViewedAt' => $first,
                'lastViewedAt' => $last,
            ];
        }

        return $out;
    }

    public function countTotalViewsFiltered(
        Document $document,
        ?\DateTimeImmutable $viewedFrom,
        ?\DateTimeImmutable $viewedTo,
        ?string $emailContains
    ): int {
        $qb = $this->createQueryBuilder('dv')
            ->select('COUNT(dv.id)')
            ->innerJoin('dv.user', 'u')
            ->andWhere('dv.document = :document')
            ->setParameter('document', $document);

        if ($viewedFrom !== null) {
            $qb->andWhere('dv.viewedAt >= :from')
                ->setParameter('from', $viewedFrom);
        }
        if ($viewedTo !== null) {
            $qb->andWhere('dv.viewedAt <= :to')
                ->setParameter('to', $viewedTo);
        }
        if ($emailContains !== null && '' !== trim($emailContains)) {
            $qb->andWhere('LOWER(u.email) LIKE :email')
                ->setParameter('email', '%'.strtolower(trim($emailContains)).'%');
        }

        $this->applyExcludeAdminUsers($qb, 'u');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Filtres période + e-mail sur les lignes document_view (dv) et l'utilisateur (u déjà joint).
     */
    private function applyViewPeriodAndEmailFilters(
        QueryBuilder $qb,
        ?\DateTimeImmutable $viewedFrom,
        ?\DateTimeImmutable $viewedTo,
        ?string $emailContains,
    ): void {
        if ($viewedFrom !== null) {
            $qb->andWhere('dv.viewedAt >= :globalFrom')
                ->setParameter('globalFrom', $viewedFrom);
        }
        if ($viewedTo !== null) {
            $qb->andWhere('dv.viewedAt <= :globalTo')
                ->setParameter('globalTo', $viewedTo);
        }
        if ($emailContains !== null && '' !== trim($emailContains)) {
            $qb->andWhere('LOWER(u.email) LIKE :globalEmail')
                ->setParameter('globalEmail', '%'.strtolower(trim($emailContains)).'%');
        }
    }

    /**
     * Totaux globaux (hors ROLE_ADMIN) sur la période et le filtre e-mail.
     *
     * @return array{totalOpens: int, distinctUsers: int, distinctDocuments: int}
     */
    public function getGlobalViewSummary(
        ?\DateTimeImmutable $viewedFrom,
        ?\DateTimeImmutable $viewedTo,
        ?string $emailContains,
    ): array {
        $qb = $this->createQueryBuilder('dv')
            ->select('COUNT(dv.id) AS totalOpens')
            ->addSelect('COUNT(DISTINCT u.id) AS distinctUsers')
            ->addSelect('COUNT(DISTINCT d.id) AS distinctDocuments')
            ->innerJoin('dv.user', 'u')
            ->innerJoin('dv.document', 'd');
        $this->applyExcludeAdminUsers($qb, 'u');
        $this->applyViewPeriodAndEmailFilters($qb, $viewedFrom, $viewedTo, $emailContains);

        $rows = $qb->getQuery()->getScalarResult();
        $row = $rows[0] ?? [];
        if ($row !== []) {
            $vals = array_values($row);
            if (\count($vals) >= 3 && !isset($row['totalOpens'])) {
                $row = ['totalOpens' => $vals[0], 'distinctUsers' => $vals[1], 'distinctDocuments' => $vals[2]];
            }
        }

        return [
            'totalOpens' => (int) ($row['totalOpens'] ?? 0),
            'distinctUsers' => (int) ($row['distinctUsers'] ?? 0),
            'distinctDocuments' => (int) ($row['distinctDocuments'] ?? 0),
        ];
    }

    /**
     * Agrégation globale par utilisateur : ouvertures, nombre de documents distincts, première / dernière vue.
     *
     * @param 'opens'|'docs'|'last' $sort
     *
     * @return list<array{userId: int, email: string, viewCount: int, distinctDocuments: int, firstViewedAt: \DateTimeImmutable, lastViewedAt: \DateTimeImmutable}>
     */
    public function aggregateGlobalViewsByUserFiltered(
        ?\DateTimeImmutable $viewedFrom,
        ?\DateTimeImmutable $viewedTo,
        ?string $emailContains,
        string $sort = 'opens',
    ): array {
        $qb = $this->createQueryBuilder('dv')
            ->innerJoin('dv.user', 'u')
            ->innerJoin('dv.document', 'd')
            ->select('u.id AS userId')
            ->addSelect('u.email AS email')
            ->addSelect('COUNT(dv.id) AS viewCount')
            ->addSelect('COUNT(DISTINCT d.id) AS distinctDocuments')
            ->addSelect('MIN(dv.viewedAt) AS firstViewedAt')
            ->addSelect('MAX(dv.viewedAt) AS lastViewedAt')
            ->groupBy('u.id')
            ->addGroupBy('u.email');

        $this->applyExcludeAdminUsers($qb, 'u');
        $this->applyViewPeriodAndEmailFilters($qb, $viewedFrom, $viewedTo, $emailContains);

        match ($sort) {
            'docs' => $qb->orderBy('distinctDocuments', 'DESC')->addOrderBy('viewCount', 'DESC')->addOrderBy('lastViewedAt', 'DESC'),
            'last' => $qb->orderBy('lastViewedAt', 'DESC')->addOrderBy('viewCount', 'DESC'),
            default => $qb->orderBy('viewCount', 'DESC')->addOrderBy('lastViewedAt', 'DESC'),
        };

        $rows = $qb->getQuery()->getScalarResult();
        $out = [];
        foreach ($rows as $row) {
            if (!isset($row['userId'], $row['email'], $row['viewCount'], $row['distinctDocuments'], $row['firstViewedAt'], $row['lastViewedAt'])) {
                $v = array_values($row);
                if (\count($v) < 6) {
                    continue;
                }
                $row = [
                    'userId' => $v[0],
                    'email' => $v[1],
                    'viewCount' => $v[2],
                    'distinctDocuments' => $v[3],
                    'firstViewedAt' => $v[4],
                    'lastViewedAt' => $v[5],
                ];
            }

            $first = $row['firstViewedAt'];
            $last = $row['lastViewedAt'];
            if ($first instanceof \DateTimeInterface) {
                $first = \DateTimeImmutable::createFromInterface($first);
            } else {
                $first = new \DateTimeImmutable((string) $first);
            }
            if ($last instanceof \DateTimeInterface) {
                $last = \DateTimeImmutable::createFromInterface($last);
            } else {
                $last = new \DateTimeImmutable((string) $last);
            }

            $out[] = [
                'userId' => (int) $row['userId'],
                'email' => (string) $row['email'],
                'viewCount' => (int) $row['viewCount'],
                'distinctDocuments' => (int) $row['distinctDocuments'],
                'firstViewedAt' => $first,
                'lastViewedAt' => $last,
            ];
        }

        return $out;
    }

    /**
     * Documents les plus consultés sur la période (mêmes filtres que le résumé ; e-mail filtre les vues des utilisateurs correspondants).
     *
     * @return list<array{documentId: int, title: string, folderName: string, viewCount: int, distinctUsers: int}>
     */
    public function findTopDocumentsByViewsFiltered(
        ?\DateTimeImmutable $viewedFrom,
        ?\DateTimeImmutable $viewedTo,
        ?string $emailContains,
        int $limit = 20,
    ): array {
        $limit = max(1, min(100, $limit));

        $qb = $this->createQueryBuilder('dv')
            ->innerJoin('dv.document', 'd')
            ->innerJoin('d.folder', 'fo')
            ->innerJoin('dv.user', 'u')
            ->select('d.id AS documentId')
            ->addSelect('d.title AS title')
            ->addSelect('fo.name AS folderName')
            ->addSelect('COUNT(dv.id) AS viewCount')
            ->addSelect('COUNT(DISTINCT u.id) AS distinctUsers')
            ->groupBy('d.id')
            ->addGroupBy('d.title')
            ->addGroupBy('fo.name')
            ->orderBy('viewCount', 'DESC')
            ->addOrderBy('d.title', 'ASC')
            ->setMaxResults($limit);

        $this->applyExcludeAdminUsers($qb, 'u');
        $this->applyViewPeriodAndEmailFilters($qb, $viewedFrom, $viewedTo, $emailContains);

        $rows = $qb->getQuery()->getScalarResult();
        $out = [];
        foreach ($rows as $row) {
            if (!isset($row['documentId'], $row['title'], $row['folderName'], $row['viewCount'], $row['distinctUsers'])) {
                $v = array_values($row);
                if (\count($v) < 5) {
                    continue;
                }
                $row = [
                    'documentId' => $v[0],
                    'title' => $v[1],
                    'folderName' => $v[2],
                    'viewCount' => $v[3],
                    'distinctUsers' => $v[4],
                ];
            }

            $out[] = [
                'documentId' => (int) $row['documentId'],
                'title' => (string) $row['title'],
                'folderName' => (string) $row['folderName'],
                'viewCount' => (int) $row['viewCount'],
                'distinctUsers' => (int) $row['distinctUsers'],
            ];
        }

        return $out;
    }
}
