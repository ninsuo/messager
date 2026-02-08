<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PhoneFormType;
use App\Repository\TriggerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly TriggerRepository $triggerRepository,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(AuthenticationUtils $authUtils): Response
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            return $this->render('home/index.html.twig', [
                'triggers' => $this->triggerRepository->findByUser($user),
            ]);
        }

        $form = $this->createForm(PhoneFormType::class, null, [
            'action' => $this->generateUrl('auth'),
        ]);

        return $this->render('home/index.html.twig', [
            'form' => $form,
            'error' => $authUtils->getLastAuthenticationError(),
        ]);
    }
}
