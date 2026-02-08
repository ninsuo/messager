<?php

namespace App\Security;

use App\Manager\UnguessableCodeManager;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class FirstFactorVerifyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UnguessableCodeManager $codeManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'verify' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $secret = $request->attributes->get('unguessableCode', '');

        /** @var array{code?: string} $formData */
        $formData = $request->request->all('verify_code_form');
        $submittedCode = preg_replace('/\s+/', '', $formData['code'] ?? '');

        if ('' === $submittedCode) {
            throw new CustomUserMessageAuthenticationException('Veuillez entrer le code reçu par SMS.');
        }

        try {
            $context = $this->codeManager->get('auth', $secret);
        } catch (\RuntimeException) {
            throw new CustomUserMessageAuthenticationException('Le code est invalide ou a expiré.');
        }

        $expectedCode = $context['code'] ?? '';

        if (!hash_equals($expectedCode, $submittedCode)) {
            throw new CustomUserMessageAuthenticationException('Le code saisi est incorrect.');
        }

        $phone = $context['phone'] ?? '';
        $user = $this->userRepository->findByPhoneNumber($phone);

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Le code saisi est incorrect.');
        }

        $this->codeManager->invalidate($secret);

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier()),
            [new RememberMeBadge()],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        $secret = $request->attributes->get('unguessableCode', '');

        return new RedirectResponse(
            $this->urlGenerator->generate('verify', ['unguessableCode' => $secret]),
        );
    }
}
