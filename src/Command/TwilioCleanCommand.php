<?php

namespace App\Command;

use App\Entity\TwilioCall;
use App\Entity\TwilioMessage;
use App\Entity\TwilioStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'twilio:clean',
    description: 'Remove Twilio entities older than 30 days',
)]
class TwilioCleanCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cutoff = new \DateTime('-30 days');

        $calls = $this->entityManager->createQuery(
            'DELETE FROM ' . TwilioCall::class . ' c WHERE c.createdAt < :cutoff'
        )->setParameter('cutoff', $cutoff)->execute();

        $messages = $this->entityManager->createQuery(
            'DELETE FROM ' . TwilioMessage::class . ' m WHERE m.createdAt < :cutoff'
        )->setParameter('cutoff', $cutoff)->execute();

        $statuses = $this->entityManager->createQuery(
            'DELETE FROM ' . TwilioStatus::class . ' s WHERE s.receivedAt < :cutoff'
        )->setParameter('cutoff', $cutoff)->execute();

        $io->success(sprintf(
            'Deleted %d call(s), %d message(s), %d status(es).',
            $calls,
            $messages,
            $statuses,
        ));

        return Command::SUCCESS;
    }
}
