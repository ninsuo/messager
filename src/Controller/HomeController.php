<?php

namespace App\Controller;

use App\Form\PhoneFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->render('home/index.html.twig');
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
