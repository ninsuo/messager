<?php

namespace App\Controller;

use App\Form\VerifyCodeFormType;
use App\Manager\UnguessableCodeManager;
use App\Provider\Call\CallProvider;
use App\Repository\FakeCallRepository;
use App\Twig\TwimlExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/verify/{unguessableCode}/call', name: 'verify_call', requirements: ['unguessableCode' => '[a-f0-9]+'], methods: ['POST'])]
    public function resendByCall(
        string $unguessableCode,
        Request $request,
        UnguessableCodeManager $codeManager,
        CallProvider $callProvider,
    ): RedirectResponse {
        $session = $request->getSession();
        $sessionKey = 'voice_call_cooldown_'.$unguessableCode;
        $lastCallAt = $session->get($sessionKey);

        if (null !== $lastCallAt && time() - $lastCallAt < 60) {
            $this->addFlash('error', 'Veuillez patienter avant de demander un nouvel appel.');

            return $this->redirectToRoute('verify', ['unguessableCode' => $unguessableCode]);
        }

        try {
            $context = $codeManager->get('auth', $unguessableCode, onlyValidate: true);
        } catch (\RuntimeException) {
            $this->addFlash('error', 'Le code est invalide ou a expiré.');

            return $this->redirectToRoute('home');
        }

        /** @var string $phone */
        $phone = $context['phone'];
        /** @var string $code */
        $code = $context['code'];

        $formattedCode = substr($code, 0, 3).' '.substr($code, 3);

        $callProvider->send($phone, ['auth_code' => $formattedCode]);

        $session->set($sessionKey, time());

        $this->addFlash('success', 'Le code vous sera communiqué par appel vocal.');

        return $this->redirectToRoute('verify', ['unguessableCode' => $unguessableCode]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('This should be handled by the security firewall.');
    }
}
