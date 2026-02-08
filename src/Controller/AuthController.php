<?php

namespace App\Controller;

use App\Form\VerifyCodeFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/auth', name: 'auth', methods: ['POST'])]
    public function auth(): Response
    {
        throw new \LogicException('This should be handled by FirstFactorTriggerAuthenticator.');
    }

    #[Route('/verify/{unguessableCode}', name: 'verify', requirements: ['unguessableCode' => '[a-f0-9]+'])]
    public function verify(string $unguessableCode, AuthenticationUtils $authUtils): Response
    {
        $form = $this->createForm(VerifyCodeFormType::class, null, [
            'action' => $this->generateUrl('verify', ['unguessableCode' => $unguessableCode]),
        ]);

        return $this->render('auth/verify.html.twig', [
            'form' => $form,
            'unguessableCode' => $unguessableCode,
            'error' => $authUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('This should be handled by the security firewall.');
    }
}
