<?php

namespace App\Service;

final class FolderTreeImportResult
{
    public function __construct(
        public readonly int $foldersCreated,
        public readonly int $documentsCreated,
    ) {
    }
}
