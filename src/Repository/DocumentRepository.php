<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentView;
use App\Entity\Folder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Trouve tous les documents d'un dossier
     * @return Document[]
     */
    public function findByFolder(Folder $folder): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.folder = :folder')
            ->setParameter('folder', $folder)
            ->orderBy('d.position', 'ASC')
            ->addOrderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un document par son titre
     */
    public function findByTitle(string $title): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.title = :title')
            ->setParameter('title', $title)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les documents de type link (liens externes)
     * @return Document[]
     */
    public function findAllLinks(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.documentType = :type')
            ->setParameter('type', Document::TYPE_LINK)
            ->orderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents d'un type spécifique
     * @return Document[]
     */
    public function findByDocumentType(string $type): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.documentType = :type')
            ->setParameter('type', $type)
            ->orderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents par mime type
     * @return Document[]
     */
    public function findByMimeType(string $mimeType): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.mimeType = :mimeType')
            ->setParameter('mimeType', $mimeType)
            ->orderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Découpe la requête utilisateur en termes (mots), pour recherche ET sur titre + text_content.
     *
     * @return list<string>
     */
    public function parseSearchTerms(string $query): array
    {
        $query = preg_replace('/\s+/u', ' ', trim($query));
        if ($query === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part, 'UTF-8') < 2) {
                continue;
            }
            $terms[] = $part;
        }

        $terms = array_values(array_unique($terms, SORT_STRING));
        if ($terms === []) {
            $single = trim($query);
            if (mb_strlen($single, 'UTF-8') >= 2) {
                return [$single];
            }
        }

        return $terms;
    }

    /**
     * Recherche plein texte sur le titre et {@see Document::getTextContent()} (PDF indexé).
     * Tous les termes doivent apparaître au moins une fois dans le titre ou dans le contenu (insensible à la casse).
     * Portable MySQL / PostgreSQL / SQLite (pas de REGEXP ni FULLTEXT requis).
     *
     * @return Document[] triés par pertinence décroissante puis titre
     */
    public function searchByQuery(string $query): array
    {
        $terms = $this->parseSearchTerms($query);
        if ($terms === []) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $table = $conn->quoteSingleIdentifier($this->getClassMetadata()->getTableName());

        $conditions = [];
        $params = [];
        foreach ($terms as $i => $term) {
            $param = 'st_'.$i;
            $like = '%'.mb_strtolower($this->escapeLikePattern($term), 'UTF-8').'%';
            $conditions[] = sprintf(
                '(LOWER(d.title) LIKE :%1$s OR LOWER(COALESCE(d.text_content, \'\')) LIKE :%1$s)',
                $param
            );
            $params[$param] = $like;
        }

        $sql = 'SELECT d.id FROM '.$table.' d WHERE '.implode(' AND ', $conditions);
        $ids = $conn->executeQuery($sql, $params)->fetchFirstColumn();
        if ($ids === []) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = array_map(static fn ($id) => (int) $id, $ids);

        $documents = $this->findBy(['id' => $ids]);
        usort($documents, function (Document $a, Document $b) use ($query, $terms): int {
            $sa = $this->scoreSearchRelevance($a, $query, $terms);
            $sb = $this->scoreSearchRelevance($b, $query, $terms);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return strcasecmp($a->getTitle(), $b->getTitle());
        });

        return $documents;
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param list<string> $terms
     */
    private function scoreSearchRelevance(Document $document, string $rawQuery, array $terms): int
    {
        $title = mb_strtolower($document->getTitle(), 'UTF-8');
        $content = mb_strtolower((string) ($document->getTextContent() ?? ''), 'UTF-8');
        $phrase = mb_strtolower(trim($rawQuery), 'UTF-8');

        $score = 0;
        if ($phrase !== '' && str_contains($title, $phrase)) {
            $score += 5_000;
        }

        foreach ($terms as $term) {
            $t = mb_strtolower($term, 'UTF-8');
            if ($t === '') {
                continue;
            }
            if (str_contains($title, $t)) {
                $score += 500;
            }
            if ($content !== '' && str_contains($content, $t)) {
                $score += min(120, substr_count($content, $t) * 8);
            }
        }

        return $score;
    }

    /**
     * Récupère les documents favoris d'un utilisateur
     * @return Document[]
     */
    public function findFavoritesByUser($user): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('App\Entity\Favorite', 'f', 'WITH', 'f.document = d.id')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les 3 documents avec le plus de vues, ou les 3 premiers documents si aucune vue
     * @return Document[]
     */
    public function findTopViewedDocuments(int $limit = 3): array
    {
        $adminIds = $this->getEntityManager()->getRepository(User::class)->findAdminRoleUserIds();

        $qb = $this->createQueryBuilder('d');
        if ($adminIds === []) {
            $qb->leftJoin(DocumentView::class, 'dv', 'WITH', 'dv.document = d.id');
        } else {
            $qb->leftJoin(
                DocumentView::class,
                'dv',
                'WITH',
                'dv.document = d.id AND IDENTITY(dv.user) NOT IN (:adminUserIds)'
            )->setParameter('adminUserIds', $adminIds, ArrayParameterType::INTEGER);
        }

        $qb->groupBy('d.id')
            ->orderBy('COUNT(dv.id)', 'DESC')
            ->addOrderBy('d.title', 'ASC')
            ->setMaxResults($limit);

        $documents = $qb->getQuery()->getResult();

        // Si aucun document trouvé, on récupère les premiers documents
        if (empty($documents)) {
            $documents = $this->createQueryBuilder('d')
                ->orderBy('d.title', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        return $documents;
    }
}
