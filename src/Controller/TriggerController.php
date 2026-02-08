<?php

namespace App\Controller;

use App\Entity\Book;
use App\Form\TriggerCreateFormType;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TriggerController extends AbstractController
{
    #[Route('/trigger/create', name: 'trigger_create', methods: ['GET'])]
    public function create(BookRepository $bookRepository): Response
    {
        $allBooks = $bookRepository->findAll();

        $form = $this->createForm(TriggerCreateFormType::class);

        /** @var array<string, Book> $booksByUuid */
        $booksByUuid = [];
        foreach ($allBooks as $book) {
            $booksByUuid[$book->getUuid()] = $book;
        }

        return $this->render('trigger/create.html.twig', [
            'form' => $form,
            'books' => $booksByUuid,
        ]);
    }
}
