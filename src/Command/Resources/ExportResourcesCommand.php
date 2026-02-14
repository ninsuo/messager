<?php

namespace App\Command\Resources;

use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'resources:export',
    description: 'Export users, contacts and books as JSON for backup or migration',
)]
class ExportResourcesCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ContactRepository $contactRepository,
        private readonly BookRepository $bookRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = [];
        foreach ($this->userRepository->findAll() as $user) {
            $users[] = [
                'phoneNumber' => $user->getPhoneNumber(),
                'isAdmin' => $user->isAdmin(),
            ];
        }

        $contacts = [];
        foreach ($this->contactRepository->findAll() as $contact) {
            $contacts[] = [
                'phoneNumber' => $contact->getPhoneNumber(),
            ];
        }

        $books = [];
        foreach ($this->bookRepository->findAll() as $book) {
            $bookContacts = [];
            foreach ($book->getContacts() as $contact) {
                $bookContacts[] = $contact->getPhoneNumber();
            }

            $books[] = [
                'name' => $book->getName(),
                'contacts' => $bookContacts,
            ];
        }

        $output->writeln((string) json_encode([
            'users' => $users,
            'contacts' => $contacts,
            'books' => $books,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }
}
