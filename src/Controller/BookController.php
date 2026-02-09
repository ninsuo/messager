<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Contact;
use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Tool\Phone;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
#[Route('/book')]
class BookController extends AbstractController
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly ContactRepository $contactRepository,
    ) {
    }

    #[Route('/{uuid}/edit', name: 'book_edit', methods: ['GET'])]
    public function edit(#[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book): Response
    {
        return $this->render('book/edit.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/{uuid}/update', name: 'book_update', methods: ['POST'])]
    public function update(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book,
        Request $request,
    ): Response {
        $name = trim($request->request->getString('name'));

        if ('' === $name) {
            $this->addFlash('error', 'Le nom de la liste de contacts ne peut pas être vide.');

            return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
        }

        $book->setName($name);
        $this->bookRepository->save($book);

        $this->addFlash('success', 'Liste de contacts mise à jour.');

        return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
    }

    #[Route('/{uuid}/contact/add', name: 'book_contact_add', methods: ['POST'])]
    public function addContact(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book,
        Request $request,
    ): Response {
        $phone = Phone::normalize(trim($request->request->getString('phone')));

        if (null === $phone) {
            $this->addFlash('error', 'Numéro de téléphone français invalide.');

            return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
        }

        $contact = $this->contactRepository->findByPhoneNumber($phone);

        if (null === $contact) {
            $contact = new Contact();
            $contact->setUuid(Uuid::v4()->toRfc4122());
            $contact->setPhoneNumber($phone);
            $this->contactRepository->save($contact);
        }

        if ($book->getContacts()->contains($contact)) {
            $this->addFlash('error', 'Ce contact est déjà dans la liste.');

            return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
        }

        $book->addContact($contact);
        $this->bookRepository->save($book);

        $this->addFlash('success', 'Contact ajouté.');

        return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
    }

    #[Route('/{uuid}/contact/{contactUuid}/remove', name: 'book_contact_remove', methods: ['POST'])]
    public function removeContact(
        #[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book,
        string $contactUuid,
    ): Response {
        foreach ($book->getContacts() as $contact) {
            if ($contact->getUuid() === $contactUuid) {
                $book->removeContact($contact);
                $this->bookRepository->save($book);

                $this->addFlash('success', 'Contact retiré.');

                return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
            }
        }

        $this->addFlash('error', 'Contact introuvable.');

        return $this->redirectToRoute('book_edit', ['uuid' => $book->getUuid()]);
    }

    #[Route('/{uuid}/delete', name: 'book_delete', methods: ['POST'])]
    public function delete(#[MapEntity(mapping: ['uuid' => 'uuid'])] Book $book): Response
    {
        $this->bookRepository->remove($book);

        $this->addFlash('success', 'Liste de contacts supprimée.');

        return $this->redirectToRoute('trigger_create');
    }
}
