<?php

namespace App\Controller;

use App\Entity\Trigger;
use App\Entity\User;
use App\Form\PhoneFormType;
use App\Repository\MessageRepository;
use App\Repository\TriggerRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly TriggerRepository $triggerRepository,
        private readonly MessageRepository $messageRepository,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(AuthenticationUtils $authUtils): Response
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            $triggers = $this->triggerRepository->findByUser($user);
            $statusCounts = $this->messageRepository->getStatusCountsByUser($user);

            return $this->render('home/index.html.twig', [
                'triggers' => $triggers,
                'statusCounts' => $statusCounts,
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

    #[Route('/trigger/{uuid}/status', name: 'trigger_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function triggerStatus(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Trigger $trigger,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User || $trigger->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $counts = $this->messageRepository->getStatusCountsByTrigger($trigger);

        return $this->render('home/_trigger_card.html.twig', [
            'trigger' => $trigger,
            'counts' => $counts,
        ]);
    }
}
