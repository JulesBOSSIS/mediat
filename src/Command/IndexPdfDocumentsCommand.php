<?php

namespace App\Command;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\PdfExtractor;
use App\Service\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IndexPdfDocumentsCommand extends Command
{
    protected static $defaultName = 'app:index-pdf';
    protected static $defaultDescription = 'Extrait et indexe le texte des PDFs existants en base de données';

    public function __construct(
        private DocumentRepository $documentRepository,
        private PdfExtractor $pdfExtractor,
        private FileManager $fileManager,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:index-pdf')
            ->setDescription('Extrait le texte de tous les PDFs et les indexe en base de données')
            ->setHelp('Cette commande traite tous les documents PDF qui n\'ont pas encore de texte extrait')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force la réindexation de tous les PDFs, même ceux déjà traités'
            )
            ->addOption(
                'id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Traiter uniquement le document avec cet ID'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Indexation des PDFs');

        $force = $input->getOption('force');
        $singleId = $input->getOption('id');

        // Récupérer les documents à traiter
        if ($singleId) {
            $document = $this->documentRepository->find($singleId);
            if (!$document) {
                $io->error(sprintf('Document avec l\'ID %d non trouvé', $singleId));
                return Command::FAILURE;
            }
            $documents = [$document];
            $io->info(sprintf('Traitement du document ID %d', $singleId));
        } else {
            $qb = $this->documentRepository->createQueryBuilder('d')
                ->where('d.documentType = :type')
                ->setParameter('type', Document::TYPE_FILE)
                ->andWhere('d.mimeType = :mimeType')
                ->setParameter('mimeType', 'application/pdf');

            if (!$force) {
                $qb->andWhere('d.textContent IS NULL');
            }

            $documents = $qb->getQuery()->getResult();
        }

        $total = count($documents);
        
        if ($total === 0) {
            $io->success('Aucun document à traiter.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Traitement de %d document(s)...', $total));
        $io->newLine();

        $progressBar = $io->createProgressBar($total);
        $progressBar->setFormat(
            ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%'
        );

        $successCount = 0;
        $errorCount = 0;
        $skipCount = 0;

        foreach ($documents as $document) {
            try {
                $progressBar->setMessage($document->getTitle());

                // Vérifier que le fichier existe
                if (!$document->getPath()) {
                    $progressBar->advance();
                    $skipCount++;
                    continue;
                }

                $fullPath = $this->fileManager->getFullPath($document->getPath());

                if (!file_exists($fullPath)) {
                    $progressBar->advance();
                    $skipCount++;
                    continue;
                }

                // Extraire le texte
                $textContent = $this->pdfExtractor->extractTextFromPdf($fullPath);

                if ($textContent) {
                    $document->setTextContent($textContent);
                    $this->entityManager->persist($document);
                    $successCount++;
                    $progressBar->setMessage(
                        sprintf(
                            '<info>%s</info> (%d car)',
                            $document->getTitle(),
                            strlen($textContent)
                        )
                    );
                } else {
                    $errorCount++;
                    $progressBar->setMessage(
                        sprintf('<error>%s</error> - extraction échouée', $document->getTitle())
                    );
                }
            } catch (\Exception $e) {
                $errorCount++;
                $progressBar->setMessage(
                    sprintf('<error>%s</error> - %s', $document->getTitle(), $e->getMessage())
                );
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // Sauvegarder les modifications
        if ($successCount > 0) {
            $this->entityManager->flush();
        }

        // Afficher les résultats
        $io->section('Résultats');
        $io->writeln(sprintf('✅ Traités avec succès : <fg=green>%d</>', $successCount));
        $io->writeln(sprintf('❌ Erreurs : <fg=red>%d</>', $errorCount));
        $io->writeln(sprintf('⊘ Ignorés : <fg=yellow>%d</>', $skipCount));
        $io->newLine();

        if ($successCount > 0) {
            $io->success(sprintf('%d document(s) indexé(s) avec succès!', $successCount));
            return Command::SUCCESS;
        }

        if ($errorCount > 0) {
            $io->warning(sprintf('%d document(s) en erreur', $errorCount));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
