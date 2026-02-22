<?php

namespace App\Controller;

use App\Repository\Fake\FakeCallRepository;
use App\Repository\Fake\FakeSmsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
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
        ]);
    }
}
