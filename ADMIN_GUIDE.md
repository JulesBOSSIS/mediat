# MediaT - Guide d'Administration

Documentation destinée aux administrateurs du site MediaT pour la gestion des utilisateurs, des dossiers, des documents et des droits d'accès.

## Table des matières

- [Accès au Panneau d'Administration](#accès-au-panneau-dadministration)
- [Gestion des Utilisateurs](#gestion-des-utilisateurs)
  - [Accepter une demande de création de compte](#accepter-une-demande-de-création-de-compte)
  - [Créer un utilisateur manuellement](#créer-un-utilisateur-manuellement)
  - [Modifier un utilisateur](#modifier-un-utilisateur)
  - [Supprimer un utilisateur](#supprimer-un-utilisateur)
  - [Système de rôles et droits](#système-de-rôles-et-droits)
- [Gestion des Dossiers](#gestion-des-dossiers)
  - [Créer un dossier](#créer-un-dossier)
  - [Organiser les dossiers en arborescence](#organiser-les-dossiers-en-arborescence)
  - [Définir les droits d'accès aux dossiers](#définir-les-droits-daccès-aux-dossiers)
  - [Modifier un dossier](#modifier-un-dossier)
  - [Supprimer un dossier](#supprimer-un-dossier)
- [Gestion des Documents](#gestion-des-documents)
  - [Ajouter un document](#ajouter-un-document)
  - [Types de documents supportés](#types-de-documents-supportés)
  - [Modifier un document](#modifier-un-document)
  - [Supprimer un document](#supprimer-un-document)
- [Gestion des Commentaires](#gestion-des-commentaires)
- [Questions Fréquemment Posées](#questions-fréquemment-posées)

---

## Accès au Panneau d'Administration

### Authentification

1. Accédez à la page de connexion du site
2. Identifiez-vous avec vos identifiants d'administrateur (email et mot de passe)
3. Une fois connecté, rendez-vous à l'adresse : `/admin`

**Important :** Seuls les utilisateurs ayant le rôle **ROLE_ADMIN** peuvent accéder au panneau d'administration.

### Le Panneau d'Administration

Une fois connecté, vous arrivez sur la page principale du panneau d'administration qui vous propose des accès rapides à :
- **Gestion des utilisateurs**
- **Gestion du contenu** (dossiers et documents)

---

## Gestion des Utilisateurs

### Accepter une demande de création de compte

Lorsqu'un utilisateur demande à créer un compte via le formulaire d'inscription, sa demande est mise en attente jusqu'à validation par un administrateur.

#### Étapes pour valider une inscription :

1. Cliquez sur le bouton **"Valider les inscriptions"** dans le panneau d'administration
2. Vous verrez la liste des demandes d'inscription en attente
3. Pour chaque demande, vous avez deux options :
   - **✅ Accepter** : Crée un compte utilisateur avec les identifiants fournis (rôle ROLE_USER par défaut)
   - **❌ Rejeter** : Refuse la demande d'inscription (l'utilisateur peut en soumettre une nouvelle)

#### Après validation :
- L'utilisateur reçoit une confirmation
- Son compte devient actif et il peut se connecter immédiatement
- Il obtient automatiquement le rôle **ROLE_USER** (droits de base)

---

### Créer un utilisateur manuellement

Vous pouvez créer des utilisateurs directement sans passer par le formulaire d'inscription.

#### Étapes :

1. Cliquez sur **"Créer un utilisateur"** dans le panneau d'administration
2. Remplissez le formulaire avec les informations suivantes :
   - **Email** : Adresse email unique de l'utilisateur (doit être unique dans le système)
   - **Mot de passe** : Mot de passe initial sécurisé
   - **Rôle** : Choisissez parmi les rôles disponibles (voir [Système de rôles](#système-de-rôles-et-droits))
3. Cliquez sur **"Créer"**
4. L'utilisateur peut maintenant se connecter avec son email et son mot de passe

**💡 Conseil :** Les utilisateurs créés manuellement sont actifs immédiatement, contrairement aux demandes d'inscription qui doivent être validées.

---

### Modifier un utilisateur

Pour mettre à jour les informations d'un utilisateur existant :

1. Cliquez sur **"Gérer les utilisateurs"** pour voir la liste complète
2. Cliquez sur l'utilisateur que vous souhaitez modifier
3. Modifiez les champs suivants :
   - **Email** : Changez l'adresse email (doit rester unique)
   - **Mot de passe** : Laissez vide pour ne pas le changer, sinon entrez un nouveau mot de passe
   - **Rôle** : Modifiez le niveau d'accès si nécessaire

4. Cliquez sur **"Modifier"** pour enregistrer les modifications

**⚠️ Attention :** 
- Changer le rôle d'un utilisateur affecte immédiatement ses droits d'accès

---

### Supprimer un utilisateur

Pour supprimer définitivement un utilisateur :

1. Allez dans **"Gérer les utilisateurs"**
2. Cliquez sur l'utilisateur à supprimer
3. Cliquez sur le bouton **"Supprimer"**
4. Confirmez la suppression

**⚠️ Attention :** La suppression est définitive et ne peut pas être annulée. Les données de l'utilisateur (commentaires, notes) seront supprimées automatiquement.

---

### Système de rôles et droits

MediaT utilise un système de rôles pour contrôler les accès et les droits des utilisateurs.

#### Les trois rôles disponibles :

##### 1. **ROLE_USER** (Utilisateur standard)
- **Rôle par défaut** attribué à tous les nouveaux utilisateurs
- **Droits d'accès** :
  - ✅ Consulter les documents publics et ceux accessibles à son rôle
  - ✅ Commenter et noter les documents
  - ✅ Marquer les documents comme favoris
  - ✅ Accéder à la fonction de recherche
  - ❌ Créer, modifier ou supprimer des documents
  - ❌ Accéder au panneau d'administration
  - ❌ Gérer les utilisateurs

##### 2. **ROLE_PARTNER** (Accès étendu - Partenaire)
- **Rôle pour les partenaires** ou utilisateurs privilégiés
- **Droits supplémentaires par rapport à ROLE_USER** :
  - ✅ Accès à des dossiers et documents réservés au rôle partenaire
  - ✅ Tous les droits de ROLE_USER
  - ❌ Pas d'accès à l'administration

##### 3. **ROLE_ADMIN** (Administrateur)
- **Rôle complet** avec tous les droits
- **Droits d'accès** :
  - ✅ **Accès total au panneau d'administration**
  - ✅ Créer, modifier, supprimer des utilisateurs
  - ✅ Valider les demandes d'inscription
  - ✅ Créer, modifier, supprimer des dossiers et documents
  - ✅ Modérer les commentaires
  - ✅ Configurer les droits d'accès aux ressources
  - ✅ Tous les droits des utilisateurs standards

#### Hiérarchie des rôles :

```
ROLE_USER (accès de base)
    ↓
ROLE_PARTNER (accès étendu)
    ↓
ROLE_ADMIN (accès complet)
```

**💡 Conseil :** 
- Attribuez ROLE_PARTNER aux partenaires et utilisateurs de confiance
- Limitez le nombre d'administrateurs pour la sécurité
- Pensez à révoquer les rôles lorsqu'un utilisateur quitte l'organisation

---

## Gestion des Dossiers

Les dossiers permettent d'organiser les documents de manière hiérarchique, comme une arborescence de répertoires.

### Créer un dossier

#### Étapes :

1. Cliquez sur **"Créer un dossier"** dans le panneau d'administration
2. Remplissez les informations suivantes :
   - **Nom** : Le nom affiché du dossier (ex: "Documentation", "Guides utilisateur")
   - **Dossier parent** : Optionnel - choisissez un dossier parent pour créer une hiérarchie (sous-dossier)
   - **Position** : Numéro pour ordonner l'affichage parmi les frères (par défaut: 0)
   - **Slug** : URL-friendly (généré automatiquement à partir du nom si vide)
   - **Rôles requis** : Définissez les droits d'accès (voir section suivante)

3. Cliquez sur **"Créer"**

#### Exemple de structure :
```
Documentation (niveau 1)
├── Guides utilisateur (niveau 2)
│   ├── Installation
│   └── Configuration
├── FAQ (niveau 2)
└── Ressources (niveau 2)
    ├── Templates
    └── Images
```

---

### Organiser les dossiers en arborescence

Les dossiers peuvent être imbriqués pour créer une structure hiérarchique.

#### Pour créer un sous-dossier :

1. Depuis la liste des dossiers, trouvez le dossier parent
2. Cliquez sur **"Créer un sous-dossier"** (ou utilisez le paramètre parentId lors de la création)
3. Remplissez les informations du nouveau dossier
4. Le nouveau dossier sera automatiquement associé au parent

#### Pour modifier la hiérarchie :

1. Éditez un dossier existant
2. Modifiez le champ **"Dossier parent"**
3. Enregistrez les modifications

---

### Définir les droits d'accès aux dossiers

Vous pouvez restreindre l'accès aux dossiers en fonction des rôles des utilisateurs.

#### Lors de la création ou modification d'un dossier :

1. Accédez aux paramètres du dossier
2. Consultez le champ **"Rôles requis"**
3. Sélectionnez les rôles autorisés à accéder au dossier :
   - **Pas de restriction** (vide) : Accessible à tous les utilisateurs connectés (minimum ROLE_USER)
   - **ROLE_USER** : Accès pour les utilisateurs avec ce rôle ou supérieur
   - **ROLE_PARTNER** : Accès réservé aux utilisateurs avec ce rôle ou ROLE_ADMIN
   - **ROLE_ADMIN** : Accès réservé aux administrateurs uniquement

**⚠️ Important :** 
- **Un utilisateur doit être connecté** pour accéder à n'importe quel contenu
- Les sous-dossiers héritent des restrictions du parent
- Un utilisateur doit avoir les droits pour voir un dossier ET pour le traverser
- Les droits s'appliquent instantanément

---

### Modifier un dossier

Pour mettre à jour un dossier existant :

1. Cliquez sur **"Gérer les dossiers"**
2. Sélectionnez le dossier à modifier
3. Modifiez les informations souhaitées (nom, position, rôles requis, etc.)
4. Cliquez sur **"Modifier"** pour enregistrer

---

### Supprimer un dossier

Pour supprimer un dossier :

1. Cliquez sur **"Gérer les dossiers"**
2. Sélectionnez le dossier à supprimer
3. Cliquez sur **"Supprimer"**
4. Confirmez la suppression

**⚠️ Attention :** 
- La suppression supprime aussi tous les sous-dossiers et documents contenus
- Cette action est définitive

---

## Gestion des Documents

Les documents sont les fichiers (PDF, Word, images) ou liens externes que vous souhaitez partager via la plateforme.

### Ajouter un document

#### Étapes :

1. Cliquez sur **"Ajouter un document"** dans le panneau d'administration
2. Remplissez le formulaire avec :
   - **Titre** : Nom du document affiché aux utilisateurs
   - **Type de document** : Choisissez entre :
     - **Fichier** : Téléchargez un fichier depuis votre ordinateur
     - **Lien externe** : Pointez vers une URL externe
   - **Dossier** : Sélectionnez le dossier parent où le document doit être rangé
   - **Position** : Numéro pour ordonner l'affichage (0 par défaut)
   - **Fichier/URL** : Selon le type choisi :
     - Pour un fichier : Cliquez pour sélectionner un fichier
     - Pour un lien : Entrez l'URL complète (ex: `https://example.com/ressource`)

3. Cliquez sur **"Ajouter"**

---

### Types de documents supportés

#### 📄 Fichiers téléchargés

**Formats supportés :**
- **Documents** : PDF
- **Images** : JPG, PNG, GIF, WebP
- **Vidéos** : MP4, WebM, OGV (pour les tutoriels vidéo)

**Traitement automatique :**
- Pour les fichiers PDF : Le texte est automatiquement extrait pour améliorer la recherche
- Les vidéos peuvent être lues directement depuis le navigateur

---

### Modifier un document

Pour mettre à jour un document :

1. Cliquez sur **"Gérer les documents"**
2. Sélectionnez le document à modifier
3. Modifiez les informations (titre, dossier, position, etc.)
4. Pour remplacer le fichier : Sélectionnez un nouveau fichier (l'ancien sera supprimé)
5. Cliquez sur **"Modifier"** pour enregistrer

---

### Supprimer un document

Pour supprimer un document :

1. Cliquez sur **"Gérer les documents"**
2. Sélectionnez le document à supprimer
3. Cliquez sur **"Supprimer"**
4. Confirmez la suppression

**Note :** 
- Le fichier physique est automatiquement supprimé du serveur
- Cette action est définitive

---

## Gestion des Commentaires

Les commentaires sont les messages que les utilisateurs laissent sur les documents (notes, retours, questions).

### Consulter les commentaires

1. Cliquez sur **"Gérer les commentaires"** dans le panneau d'administration
2. Vous verrez une liste de tous les commentaires avec :
   - L'auteur (email de l'utilisateur)
   - Le document commenté
   - Le contenu du commentaire
   - La date de publication

### Supprimer un commentaire

Pour modérer un commentaire inapproprié :

1. Consultez la liste des commentaires
2. Cliquez sur **"Supprimer"** à côté du commentaire à retirer
3. Confirmez la suppression

**Note :** La suppression des commentaires est définitive et l'utilisateur n'en est pas averti automatiquement.

---

## Questions Fréquemment Posées

### Quels sont les droits par défaut ?

Chaque utilisateur a au minimum le rôle **ROLE_USER** qui lui permet de :
- Consulter les documents publics
- Laisser des commentaires
- Noter les documents
- Marquer les documents comme favoris

### Comment ajouter un partenaire ?

Les partenaires sont gérés comme des utilisateurs avec le rôle **ROLE_PARTNER** :

1. Allez dans **"Créer un utilisateur"** ou acceptez sa demande d'inscription
2. Attribuez-lui le rôle **ROLE_PARTNER**
3. Créez ou modifiez les dossiers/documents réservés au rôle partenaire
4. Assurez-vous que les dossiers ont **ROLE_PARTNER** dans les "Rôles requis"

Le partenaire pourra alors accéder aux ressources qui lui sont destinées.

### Puis-je changer les droits d'un utilisateur ?

Oui, vous pouvez modifier le rôle d'un utilisateur à tout moment :

1. Allez dans **"Gérer les utilisateurs"**
2. Sélectionnez l'utilisateur
3. Modifiez le champ **"Rôle"**
4. Enregistrez les modifications

Les nouveaux droits s'appliqueront immédiatement lors de la prochaine action de l'utilisateur.

### Comment réinitialiser le mot de passe d'un utilisateur ?

1. Allez dans **"Gérer les utilisateurs"**
2. Sélectionnez l'utilisateur
3. Dans le champ **"Mot de passe"**, entrez un nouveau mot de passe
4. Enregistrez les modifications

Le nouvel utilisateur pourra se connecter avec ce nouveau mot de passe.

### Puis-je restaurer un utilisateur supprimé ?

Non, la suppression est définitive.
Si l'utilisateur doit retrouver un compte, il devra faire une nouvelle demande de création ou se le faire créer par un administrateur. 

### Que se passe-t-il quand je supprime un dossier ?

La suppression d'un dossier supprime également :
- ✓ Tous les sous-dossiers contenus
- ✓ Tous les documents du dossier et des sous-dossiers
- ✗ Les commentaires restent mais référencent des documents supprimés

### Comment organiser au mieux mes dossiers ?

**Bonnes pratiques :**
- Créez une arborescence logique et intuitive
- Limitez la profondeur à 3-4 niveaux maximum
- Utilisez des noms clairs et cohérents
- Groupez les documents par thème ou type
- Utilisez les rôles requis pour les restrictions de sensibilité

**Exemple de structure :**
```
📁 Ressources
├── 📁 Documentation
│   ├── 📄 Guide complet
│   └── 📄 FAQ
├── 📁 Partenaires (ROLE_PARTNER)
│   ├── 📄 Conditions partenariat
│   └── 📄 Outils disponibles
└── 📁 Administration (ROLE_ADMIN)
    ├── 📄 Configuration système
    └── 📄 Rapports statistiques
```

### Puis-je dupliquer un document ou un dossier ?

Actuellement, il n'y a pas de fonction de duplication. Vous devez :
- Pour un document : Créer un nouveau document manuellement
- Pour un dossier : Créer un nouveau dossier et ses documents

### Combien d'utilisateurs/documents puis-je avoir ?

Il n'y a pas de limite fixe. Cependant, pour des performances optimales :
- Gardez moins de 1000 dossiers
- Limitez les profondeurs d'arborescence à 5 niveaux
- Archivez les anciens documents régulièrement

### Comment transférer la responsabilité d'administration ?

1. Créez un nouvel administrateur : Créez un utilisateur avec le rôle **ROLE_ADMIN**
2. Testez l'accès : Vérifiez que le nouvel admin peut accéder au panneau
3. Revoyez les responsabilités : Communiquez les tâches importantes
4. Optionnel : Demandez une suppression de votre compte

### Où trouver le support technique ?

Pour toute question technique ou problème :
- Contactez l'administrateur système du projet
- Consultez la documentation technique principale (`README.md`)
- Signalez les bugs à l'équipe de développement

---

## Support et Questions

Pour toute question supplémentaire sur l'administration de MediaT :
- Contactez l'équipe en charge du projet

**Dernière mise à jour :** Décembre 2025
