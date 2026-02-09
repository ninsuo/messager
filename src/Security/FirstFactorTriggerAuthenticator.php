<?php

namespace App\Security;

use App\Manager\UnguessableCodeManager;
use App\Provider\SMS\SmsProvider;
use App\Repository\UserRepository;
use App\Security\Exception\CodeSentException;
use App\Tool\Phone;
use App\Tool\Random;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class FirstFactorTriggerAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UnguessableCodeManager $codeManager,
        private readonly SmsProvider $smsProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'TWILIO_PHONE_NUMBER')]
        private readonly string $twilioPhoneNumber,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'auth' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): never
    {
        /** @var array{phone?: string} $formData */
        $formData = $request->request->all('phone_form');
        $phone = Phone::normalize(trim($formData['phone'] ?? ''));

        if (null === $phone) {
            throw new CustomUserMessageAuthenticationException('Veuillez entrer un numéro de téléphone français valide.');
        }

        $verificationCode = Random::numeric(6);
        $formattedCode = substr($verificationCode, 0, 3).' '.substr($verificationCode, 3);

        $secret = $this->codeManager->generate('auth', [
            'phone' => $phone,
            'code' => $verificationCode,
        ], expiresInSeconds: 600, maxHitCount: 3);

        $user = $this->userRepository->findByPhoneNumber($phone);

        if ($user) {
            $this->smsProvider->send(
                $this->twilioPhoneNumber,
                $phone,
                sprintf('Votre code Messager : %s', $formattedCode),
            );
        }

        throw new CodeSentException($secret);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof CodeSentException) {
            return new RedirectResponse(
                $this->urlGenerator->generate('verify', ['unguessableCode' => $exception->getSecret()]),
            );
        }

        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('home'));
    }
}
