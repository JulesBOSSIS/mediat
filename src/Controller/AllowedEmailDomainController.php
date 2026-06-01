<?php

namespace App\Controller;

use App\Entity\AllowedEmailDomain;
use App\Form\AllowedEmailDomainFormType;
// use App\Repository\AllowedEmailDomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/email-domains')]
#[IsGranted('ROLE_ADMIN')]
class AllowedEmailDomainController extends AbstractController
{
    // #[Route('', name: 'app_admin_email_domains_list', methods: ['GET'])]
    // public function list(AllowedEmailDomainRepository $repository): Response
    // {
    //     $domains = $repository->findBy([], ['domain' => 'ASC']);

    //     return $this->render('admin/email_domains/list.html.twig', [
    //         'domains' => $domains,
    //     ]);
    // }

    #[Route('/create', name: 'app_admin_email_domains_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $domain = new AllowedEmailDomain();
        $form = $this->createForm(AllowedEmailDomainFormType::class, $domain);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($domain);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le domaine "%s" a été ajouté avec succès.', $domain->getDomain()));

            return $this->redirectToRoute('app_admin_email_domains_list');
        }

        return $this->render('admin/email_domains/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_email_domains_edit', methods: ['GET', 'POST'])]
    public function edit(
        AllowedEmailDomain $domain,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(AllowedEmailDomainFormType::class, $domain);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le domaine "%s" a été modifié avec succès.', $domain->getDomain()));

            return $this->redirectToRoute('app_admin_email_domains_list');
        }

        return $this->render('admin/email_domains/edit.html.twig', [
            'form' => $form,
            'domain' => $domain,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_email_domains_delete', methods: ['POST'])]
    public function delete(
        AllowedEmailDomain $domain,
        EntityManagerInterface $entityManager
    ): Response {
        $domainName = $domain->getDomain();
        $entityManager->remove($domain);
        $entityManager->flush();

        $this->addFlash('warning', sprintf('Le domaine "%s" a été supprimé.', $domainName));

        return $this->redirectToRoute('app_admin_email_domains_list');
    }

    #[Route('/{id}/toggle', name: 'app_admin_email_domains_toggle', methods: ['POST'])]
    public function toggle(
        AllowedEmailDomain $domain,
        EntityManagerInterface $entityManager
    ): Response {
        $domain->setIsActive(!$domain->isActive());
        $entityManager->flush();

        $status = $domain->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', sprintf('Le domaine "%s" a été %s.', $domain->getDomain(), $status));

        return $this->redirectToRoute('app_admin_email_domains_list');
    }
}
