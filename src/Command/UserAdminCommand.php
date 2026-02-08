<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'user:admin',
    description: 'Grant or revoke admin rights for a user',
)]
class UserAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number of the user')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Revoke admin rights instead of granting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $phone = $input->getArgument('phone');
        $user = $this->userRepository->findByPhoneNumber($phone);

        if (!$user) {
            $output->writeln('No user found with this phone number.');

            return Command::FAILURE;
        }

        $revoke = $input->getOption('revoke');
        $user->setIsAdmin(!$revoke);

        $this->userRepository->save($user);

        $output->writeln(sprintf(
            'Admin rights %s for user %s.',
            $revoke ? 'revoked' : 'granted',
            $user->getUuid(),
        ));

        return Command::SUCCESS;
    }
}
