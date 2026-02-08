<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'user:create',
    description: 'Create a new user with the given phone number',
)]
class UserCreateCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number (e.g. +33612345678)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant admin rights');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $phone = $input->getArgument('phone');

        if ($this->userRepository->findByPhoneNumber($phone)) {
            $output->writeln('A user with this phone number already exists.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setUuid(Uuid::v4()->toRfc4122());
        $user->setPhoneNumber($phone);
        $user->setIsAdmin($input->getOption('admin'));

        $this->userRepository->save($user);

        $output->writeln(sprintf('User created (uuid: %s).', $user->getUuid()));

        return Command::SUCCESS;
    }
}
