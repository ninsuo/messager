<?php

namespace App\Command\Resources;

use App\Entity\Book;
use App\Entity\Contact;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'resources:import',
    description: 'Import users, contacts and books from a JSON string',
)]
class ImportResourcesCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ContactRepository $contactRepository,
        private readonly BookRepository $bookRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('json', InputArgument::REQUIRED, 'JSON string containing resources to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = json_decode($input->getArgument('json'), true);

        if (!is_array($data)) {
            $output->writeln('Invalid JSON format.');

            return Command::FAILURE;
        }

        $usersCreated = 0;
        $usersSkipped = 0;
        foreach ($data['users'] ?? [] as $userData) {
            $phone = $userData['phoneNumber'] ?? null;
            if (null === $phone) {
                continue;
            }

            if ($this->userRepository->findByPhoneNumber($phone)) {
                $usersSkipped++;
                continue;
            }

            $user = new User();
            $user->setUuid(Uuid::v4()->toRfc4122());
            $user->setPhoneNumber($phone);
            $user->setIsAdmin($userData['isAdmin'] ?? false);
            $this->userRepository->save($user);
            $usersCreated++;
        }

        $contactsByPhone = [];
        $contactsCreated = 0;
        $contactsSkipped = 0;
        foreach ($data['contacts'] ?? [] as $contactData) {
            $phone = $contactData['phoneNumber'] ?? null;
            if (null === $phone) {
                continue;
            }

            $existing = $this->contactRepository->findByPhoneNumber($phone);
            if ($existing) {
                $contactsByPhone[$phone] = $existing;
                $contactsSkipped++;
                continue;
            }

            $contact = new Contact();
            $contact->setUuid(Uuid::v4()->toRfc4122());
            $contact->setPhoneNumber($phone);
            $this->contactRepository->save($contact);
            $contactsByPhone[$phone] = $contact;
            $contactsCreated++;
        }

        $booksCreated = 0;
        foreach ($data['books'] ?? [] as $bookData) {
            $name = $bookData['name'] ?? null;
            if (null === $name) {
                continue;
            }

            $book = new Book();
            $book->setUuid(Uuid::v4()->toRfc4122());
            $book->setName($name);

            foreach ($bookData['contacts'] ?? [] as $contactPhone) {
                $contact = $contactsByPhone[$contactPhone]
                    ?? $this->contactRepository->findByPhoneNumber($contactPhone);

                if ($contact) {
                    $book->addContact($contact);
                }
            }

            $this->bookRepository->save($book);
            $booksCreated++;
        }

        $output->writeln(sprintf('Users: %d created, %d skipped (already exist).', $usersCreated, $usersSkipped));
        $output->writeln(sprintf('Contacts: %d created, %d skipped (already exist).', $contactsCreated, $contactsSkipped));
        $output->writeln(sprintf('Books: %d created.', $booksCreated));

        return Command::SUCCESS;
    }
}
