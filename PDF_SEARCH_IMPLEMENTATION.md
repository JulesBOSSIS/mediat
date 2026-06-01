# Implémentation de la Recherche PDF avec Indexation

## Résumé des modifications

Système complet d'extraction et de recherche de texte dans les PDFs avec REGEXP MySQL pour la correspondance de mots entiers.

## Packages installés

```bash
composer require smalot/pdfparser
composer require beberlei/doctrineextensions
```

## Changements apportés

### 1. **Entity Document** (`src/Entity/Document.php`)
- ✅ Ajout du champ `textContent` (type TEXT, nullable)
- ✅ Getter/Setter `getTextContent()` et `setTextContent()`

### 2. **Service PdfExtractor** (`src/Service/PdfExtractor.php`)
- ✅ Nouvelle classe pour extraire le texte des PDFs
- ✅ Nettoyage automatique du texte (espaces multiples, nouvelles lignes excessives)
- ✅ Gestion des erreurs et logging
- ✅ Méthode `extractTextFromPdf(string $filePath): ?string`

### 3. **AdminController** (`src/Controller/AdminController.php`)
- ✅ Injection du service `PdfExtractor`
- ✅ Extraction du texte lors de la création d'un document PDF
- ✅ Extraction du texte lors de la modification d'un document PDF
- ✅ Stockage automatique du contenu dans `textContent`

### 4. **DocumentRepository** (`src/Repository/DocumentRepository.php`)
- ✅ Mise à jour de `searchByQuery()` pour utiliser REGEXP
- ✅ Pattern avec word boundaries : `[[:<:]]mot[[:>:]]`
- ✅ Recherche dans le titre (LIKE) ET le contenu (REGEXP)
- ✅ Échappe automatiquement les caractères spéciaux regex

### 5. **Configuration Doctrine** (`config/packages/doctrine.yaml`)
- ✅ Activation de la fonction DQL `REGEXP` pour MySQL

### 6. **Migration Doctrine** (`migrations/Version20251202122351.php`)
- ✅ Ajout de la colonne `text_content` à la table `document`

## Fonctionnement

### Lors de l'upload d'un PDF :
1. Le fichier est uploadé via le contrôleur AdminController
2. Le service PdfExtractor extrait le texte du PDF
3. Le texte nettoyé est stocké dans le champ `textContent`
4. Le document est sauvegardé en base de données

### Lors de la recherche :
1. L'utilisateur tape une requête (ex: "ROC")
2. La requête est transmise au DocumentRepository
3. Deux critères sont appliqués :
   - **Titre** : Recherche LIKE (partielle) pour plus de flexibilité
   - **Contenu** : Recherche REGEXP avec word boundaries pour exact match
4. Les documents correspondent sont retournés

## Requête SQL générée

```sql
SELECT * FROM document d 
WHERE d.title LIKE '%ROC%' 
   OR d.text_content REGEXP '[[:<:]]ROC[[:>:]]'
```

## Word Boundaries en MySQL

- `[[:<:]]` : Début d'un mot
- `[[:>:]]` : Fin d'un mot

Exemple :
- ✅ Correspond : "ROC", "ROCK", "This ROC is good"
- ❌ Ne correspond pas : "PROCESS", "CROC"

## Prochaines étapes (optionnel)

Pour améliorer les performances avec de nombreux documents :

1. **Indexer le champ `textContent`**
   ```sql
   ALTER TABLE document ADD FULLTEXT INDEX ft_textContent (text_content);
   ```

2. **Utiliser la recherche FULLTEXT** pour de meilleures performances
   ```php
   WHERE MATCH(d.textContent) AGAINST(:query IN BOOLEAN MODE)
   ```

3. **Ajouter une colonne `indexed_at`** pour tracker les documents indexés

## Tests

```bash
# Générer une nouvelle migration si le schéma a changé
php bin/console make:migration

# Exécuter la migration
php bin/console doctrine:migrations:migrate

# Effacer le cache
php bin/console cache:clear
```

## Notes

- Les PDFs non valides sont loggés mais ne bloquent pas l'upload
- Le texte extrait est limité à ce qui peut être converti en UTF-8
- La recherche est insensible à la casse grâce à COLLATE utf8mb4_unicode_ci

## Indexer les PDF en BDD depuis la console

```bash
php bin/console app:index-pdf
```
