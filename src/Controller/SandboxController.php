<?php

namespace App\Controller;

use App\Entity\Fake\FakeCall;
use App\Event\TwilioEvent;
use App\Provider\Call\FakeCallProvider;
use App\Repository\Fake\FakeCallRepository;
use App\Repository\Fake\FakeSmsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[When(env: 'dev')]
#[When(env: 'test')]
#[Route('/sandbox')]
class SandboxController extends AbstractController
{
    #[Route('', name: 'sandbox', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('sandbox/index.html.twig');
    }

    #[Route('/sms', name: 'sandbox_sms', methods: ['GET'])]
    public function sms(FakeSmsRepository $fakeSmsRepository): Response
    {
        return $this->render('sandbox/sms.html.twig', [
            'messages' => $fakeSmsRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/calls', name: 'sandbox_calls', methods: ['GET'])]
    public function calls(FakeCallRepository $fakeCallRepository): Response
    {
        return $this->render('sandbox/calls.html.twig', [
            'calls' => $fakeCallRepository->findBy([], ['id' => 'DESC']),
            'latestIds' => $fakeCallRepository->findLatestIdsByPhone(),
        ]);
    }

    #[Route('/calls/{id}/key-press', name: 'sandbox_call_key_press', methods: ['POST'])]
    public function keyPress(
        FakeCall $fakeCall,
        Request $request,
        FakeCallProvider $fakeCallProvider,
    ): Response {
        $digit = $request->request->get('digit');
        $context = $fakeCall->getContext() ?? [];

        $fakeCallProvider->triggerHook(
            $fakeCall->getFromNumber(),
            $fakeCall->getToNumber(),
            $context,
            TwilioEvent::CALL_KEY_PRESSED,
            FakeCall::TYPE_KEY_PRESS,
            $digit,
        );

        return $this->redirectToRoute('sandbox_calls');
    }
}
