<?php

namespace App\Command\Twilio;

use App\Provider\SMS\SmsProvider;
use App\Tool\Phone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'twilio:sms',
    description: 'Send an SMS to the given phone number',
)]
class TwilioSmsCommand extends Command
{
    public function __construct(private readonly SmsProvider $smsProvider)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Destination phone number (e.g. +33612345678)')
            ->addArgument('message', InputArgument::REQUIRED, 'Message to send');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = Phone::normalize($input->getArgument('to'));

        if (null === $to) {
            $output->writeln('Invalid French phone number.');

            return Command::FAILURE;
        }

        $sid = $this->smsProvider->send($to, $input->getArgument('message'));

        $output->writeln(sprintf('SMS sent (sid: %s).', $sid));

        return Command::SUCCESS;
    }
}
