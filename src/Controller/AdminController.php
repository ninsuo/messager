<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminCreateUserFormType;
use App\Repository\UserRepository;
use App\Tool\Phone;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'admin', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(): Response
    {
        $form = $this->createForm(AdminCreateUserFormType::class, null, [
            'action' => $this->generateUrl('admin_users_create'),
        ]);

        return $this->render('admin/users.html.twig', [
            'users' => $this->userRepository->findAll(),
            'form' => $form,
        ]);
    }

    #[Route('/users/create', name: 'admin_users_create', methods: ['POST'])]
    public function createUser(Request $request): Response
    {
        $form = $this->createForm(AdminCreateUserFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $phone = Phone::normalize((string) $form->get('phone')->getData());

            if (null === $phone) {
                $this->addFlash('error', 'Numéro de téléphone français invalide.');

                return $this->redirectToRoute('admin_users');
            }

            if ($this->userRepository->findByPhoneNumber($phone)) {
                $this->addFlash('error', 'Un utilisateur avec ce numéro existe déjà.');

                return $this->redirectToRoute('admin_users');
            }

            $user = new User();
            $user->setUuid(Uuid::v4()->toRfc4122());
            $user->setPhoneNumber($phone);

            $this->userRepository->save($user);

            $this->addFlash('success', 'Utilisateur créé avec succès.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{uuid}/grant-admin', name: 'admin_users_grant_admin', methods: ['POST'])]
    public function grantAdmin(#[MapEntity(mapping: ['uuid' => 'uuid'])] User $user): Response
    {
        $user->setIsAdmin(true);
        $this->userRepository->save($user);

        $this->addFlash('success', 'Droits administrateur accordés.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{uuid}/revoke-admin', name: 'admin_users_revoke_admin', methods: ['POST'])]
    public function revokeAdmin(#[MapEntity(mapping: ['uuid' => 'uuid'])] User $user): Response
    {
        if ($user->getUuid() === $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('error', 'Vous ne pouvez pas retirer vos propres droits administrateur.');

            return $this->redirectToRoute('admin_users');
        }

        $user->setIsAdmin(false);
        $this->userRepository->save($user);

        $this->addFlash('success', 'Droits administrateur retirés.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{uuid}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function deleteUser(#[MapEntity(mapping: ['uuid' => 'uuid'])] User $user): Response
    {
        if ($user->getUuid() === $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('admin_users');
        }

        if ($user->isAdmin()) {
            $this->addFlash('error', 'Impossible de supprimer un administrateur. Retirez d\'abord ses droits.');

            return $this->redirectToRoute('admin_users');
        }

        $this->userRepository->remove($user);

        $this->addFlash('success', 'Utilisateur supprimé.');

        return $this->redirectToRoute('admin_users');
    }
}
