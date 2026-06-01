<?php

namespace App\Controller;

use App\Entity\RegistrationRequest;
use App\Entity\User;
use App\Form\RegistrationRequestFormType;
use App\Form\VerificationCodeFormType;
use App\Repository\RegistrationRequestRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationRequestController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function register(
        Request $request,
        PasswordHasherFactoryInterface $passwordHasherFactory,
        EntityManagerInterface $entityManager,
        RegistrationRequestRepository $registrationRequestRepository,
        EmailService $emailService
    ): Response {
        // Bloquer la création de compte sauf pour les administrateurs
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'La création de compte est désactivée. Veuillez contacter un administrateur.');
            return $this->redirectToRoute('app_login');
        }

        $registrationRequest = new RegistrationRequest();
        $form = $this->createForm(RegistrationRequestFormType::class, $registrationRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Hash the password using the default hasher configured for User
            $hasher = $passwordHasherFactory->getPasswordHasher('App\Entity\User');
            $hashedPassword = $hasher->hash($plainPassword);
            $registrationRequest->setPassword($hashedPassword);

            // Generate verification code
            $verificationCode = $emailService->generateVerificationCode();
            $registrationRequest->setVerificationCode($verificationCode);
            $registrationRequest->setCodeExpiresAt(new \DateTime('+30 minutes'));
            $registrationRequest->setIsCodeVerified(false);

            // Generate unique verification token (UUID)
            $verificationToken = $registrationRequest->generateVerificationToken();
            $registrationRequest->setVerificationToken($verificationToken);

            // Check if a request already exists for this email
            $existingRequest = $registrationRequestRepository->findByEmail($registrationRequest->getEmail());
            
            try {
                if ($existingRequest) {
                    // Update the existing request with new code
                    $existingRequest->setPassword($registrationRequest->getPassword());
                    $existingRequest->setVerificationCode($verificationCode);
                    $existingRequest->setCodeExpiresAt(new \DateTime('+30 minutes'));
                    $existingRequest->setIsCodeVerified(false);
                    $existingRequest->setCreatedAt(new \DateTime());
                    $existingRequest->setIsValidated(false);
                    $existingRequest->setVerificationToken($verificationToken);
                    
                    // Send email with verification code BEFORE saving to database
                    $emailService->sendVerificationCode($existingRequest->getEmail(), $verificationCode);
                    
                    // Only save if email was sent successfully
                    $entityManager->flush();
                    
                    return $this->redirectToRoute('app_verify_code', ['id' => $existingRequest->getId()]);
                } else {
                    // Persist the new registration request
                    $entityManager->persist($registrationRequest);
                    
                    // Send email with verification code BEFORE saving to database
                    $emailService->sendVerificationCode($registrationRequest->getEmail(), $verificationCode);
                    
                    // Only save if email was sent successfully
                    $entityManager->flush();
                    
                    return $this->redirectToRoute('app_verify_code', ['id' => $registrationRequest->getId()]);
                }
            } catch (\Exception $e) {
                // If email sending fails, don't save the registration request
                $entityManager->clear();
                
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email. Veuillez réessayer. Si le problème persiste, contactez l\'administrateur.');
                
                // Log the error for debugging
                if ($this->getParameter('kernel.environment') === 'dev') {
                    $this->addFlash('debug', 'Erreur technique : ' . $e->getMessage());
                }
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }


    #[Route('/verify-code/{id}', name: 'app_verify_code', methods: ['GET', 'POST'])]
    public function verifyCode(
        RegistrationRequest $registrationRequest,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher
    ): Response {
        // Bloquer la vérification de code sauf pour les administrateurs
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'La création de compte est désactivée. Veuillez contacter un administrateur.');
            return $this->redirectToRoute('app_login');
        }

        // Check if code is already verified
        if ($registrationRequest->isCodeVerified()) {
            $this->addFlash('info', 'Ce code a déjà été validé. Veuillez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        // Check if code has expired
        if (!$registrationRequest->isCodeValid()) {
            $this->addFlash('error', 'Le code de validation a expiré. Veuillez refaire une demande d\'inscription.');
            return $this->redirectToRoute('app_register');
        }

        $form = $this->createForm(VerificationCodeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $enteredCode = $form->get('code')->getData();

            // Verify the code
            if ($registrationRequest->getVerificationCode() === $enteredCode) {
                // Create the user
                $user = new User();
                $user->setEmail($registrationRequest->getEmail());
                $user->setPassword($registrationRequest->getPassword());
                $user->setRoles([User::ROLE_USER]);
                $user->setIsActive(true);

                // Mark registration request as verified and validated
                $registrationRequest->setIsCodeVerified(true);
                $registrationRequest->setIsValidated(true);
                $registrationRequest->setValidatedAt(new \DateTime());

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_login');
            } else {
                $this->addFlash('error', 'Code de validation incorrect. Veuillez réessayer.');
            }
        }

        return $this->render('registration/verify_code.html.twig', [
            'form' => $form,
            'email' => $registrationRequest->getEmail(),
        ]);
    }

    /**
     * API sécurisée pour récupérer le code de vérification par email
     * Cette route est destinée à être utilisée par une autre application
     * Sécurisée par clé API secrète
     * 
     * Usage:
     * GET /api/verification-code?email=user@example.com
     * Header: X-API-Key: votre-clé-secrète
     * 
     * Configuration:
     * Définir VERIFICATION_API_KEY dans votre fichier .env avec une clé secrète forte
     * Exemple: VERIFICATION_API_KEY=your-super-secret-api-key-here-min-32-chars
     */
    #[Route('/api/verification-code', name: 'app_api_verification_code', methods: ['GET'])]
    public function getVerificationCodeByEmail(
        Request $request,
        RegistrationRequestRepository $registrationRequestRepository
    ): JsonResponse {
        // Récupérer la clé API depuis les variables d'environnement
        // Utiliser une variable d'environnement dédiée pour plus de sécurité
        $expectedApiKey = $_ENV['VERIFICATION_API_KEY'] ?? null;
        
        if (!$expectedApiKey) {
            // Fallback: utiliser le secret de l'application avec un suffixe
            // ATTENTION: En production, définir VERIFICATION_API_KEY dans .env
            $expectedApiKey = $this->getParameter('kernel.secret') . '_verification_api';
        }

        // Récupérer la clé API depuis le header (priorité) ou les paramètres
        $providedApiKey = $request->headers->get('X-API-Key') 
            ?? $request->query->get('api_key')
            ?? null;

        // Vérifier la clé API avec comparaison constante de temps (protection contre timing attacks)
        if (!$providedApiKey || !hash_equals($expectedApiKey, $providedApiKey)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Clé API invalide ou manquante',
            ], 401);
        }

        // Récupérer l'email depuis les paramètres
        $email = $request->query->get('email');
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Email invalide ou manquant',
            ], 400);
        }

        // Rechercher la registration request par email
        $registrationRequest = $registrationRequestRepository->findByEmail($email);
        
        if (!$registrationRequest) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Aucune demande d\'inscription trouvée pour cet email',
            ], 404);
        }

        // Vérifier si le code est déjà vérifié
        if ($registrationRequest->isCodeVerified()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Ce code a déjà été validé',
            ], 400);
        }

        // Vérifier si le code a expiré
        if (!$registrationRequest->isCodeValid()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Le code de validation a expiré',
            ], 400);
        }

        // Retourner le code de vérification
        return new JsonResponse([
            'success' => true,
            'email' => $registrationRequest->getEmail(),
            'verification_code' => $registrationRequest->getVerificationCode(),
            'expires_at' => $registrationRequest->getCodeExpiresAt()?->format('c'),
            'created_at' => $registrationRequest->getCreatedAt()?->format('c'),
        ], 200);
    }
}
