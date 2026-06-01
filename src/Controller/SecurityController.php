<?php

namespace App\Controller;

use App\Form\ForgotPasswordFormType;
use App\Form\ResetPasswordFormType;
use App\Repository\UserRepository;
use App\Service\DemoUserService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        EmailService $emailService
    ): Response {
        // Bloquer le mot de passe oublié sauf pour les administrateurs
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'La réinitialisation de mot de passe est désactivée. Veuillez contacter un administrateur.');
            return $this->redirectToRoute('app_login');
        }

        // Si l'utilisateur est déjà connecté, rediriger vers accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                try {
                    // Générer un token aléatoire
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = new \DateTimeImmutable('+2 hours');

                    // Envoyer l'email AVANT de sauvegarder le token
                    $emailService->sendPasswordResetEmail($email, $token);

                    // Si l'email est envoyé avec succès, sauvegarder le token
                    $user->setResetPasswordToken($token);
                    $user->setResetPasswordTokenExpiresAt($expiresAt);
                    $entityManager->flush();
                } catch (\Exception $e) {
                    // Si l'envoi d'email échoue, ne pas sauvegarder le token
                    $entityManager->clear();
                    
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email de réinitialisation. Veuillez réessayer. Si le problème persiste, contactez l\'administrateur.');
                    
                    // Log the error for debugging
                    if ($this->getParameter('kernel.environment') === 'dev') {
                        $this->addFlash('debug', 'Erreur technique : ' . $e->getMessage());
                    }
                    
                    return $this->render('security/forgot_password.html.twig', [
                        'form' => $form,
                    ]);
                }
            }

            // Toujours afficher un message de succès (pour des raisons de sécurité)
            $this->addFlash('success', 'Un email de réinitialisation de mot de passe a été envoyé à cette adresse (si elle existe dans notre système).');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(string $token, Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        // Bloquer la réinitialisation de mot de passe sauf pour les administrateurs
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'La réinitialisation de mot de passe est désactivée. Veuillez contacter un administrateur.');
            return $this->redirectToRoute('app_login');
        }

        // Si l'utilisateur est déjà connecté, rediriger vers accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        $user = $userRepository->findOneBy(['resetPasswordToken' => $token]);

        if (!$user || !$user->isResetPasswordTokenValid()) {
            $this->addFlash('error', 'Ce lien de réinitialisation de mot de passe est invalide ou a expiré.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('plainPassword')->getData();

            // Hasher et définir le nouveau mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setResetPasswordToken(null);
            $user->setResetPasswordTokenExpiresAt(null);

            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
            'token' => $token,
        ]);
    }

    #[Route(path: '/', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        #[Autowire('%app.demo_mode%')] bool $demoMode,
    ): Response {
        // Si l'utilisateur est déjà connecté, rediriger vers accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Traduire le message d'erreur en français
        $errorMessage = null;
        if ($error) {
            $errorMessage = 'Identifiants invalides.';
        }

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'error_message' => $errorMessage,
            'demo_mode' => $demoMode,
        ]);
    }

    #[Route(path: '/demo/login', name: 'app_demo_login', methods: ['POST'])]
    public function demoLogin(
        Request $request,
        Security $security,
        DemoUserService $demoUserService,
        #[Autowire('%app.demo_mode%')] bool $demoMode,
    ): Response {
        if (!$demoMode) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('demo_login', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        $user = $demoUserService->getOrCreateDemoUser();
        $security->login($user, 'form_login');

        $this->addFlash('info', 'Vous explorez MediaT en mode démonstration (même accès qu\'un utilisateur standard).');

        return $this->redirectToRoute('app_accueil');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
