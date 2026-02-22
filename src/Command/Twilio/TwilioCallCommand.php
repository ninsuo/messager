<?php

namespace App\Command\Twilio;

use App\Provider\Call\CallProvider;
use App\Tool\Phone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'twilio:call',
    description: 'Make a voice call to the given phone number',
)]
class TwilioCallCommand extends Command
{
    public function __construct(private readonly CallProvider $callProvider)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Destination phone number (e.g. +33612345678)')
            ->addArgument('message', InputArgument::REQUIRED, 'Message to speak');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = Phone::normalize($input->getArgument('to'));

        if (null === $to) {
            $output->writeln('Invalid French phone number.');

            return Command::FAILURE;
        }

        $sid = $this->callProvider->send($to, $input->getArgument('message'));

        $output->writeln(sprintf('Call initiated (sid: %s).', $sid));

        return Command::SUCCESS;
    }
}
