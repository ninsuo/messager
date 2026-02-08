<?php

namespace App\Command;

use App\Manager\TwilioMessageManager;
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
    public function __construct(
        private readonly TwilioMessageManager $messageManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('from', InputArgument::REQUIRED, 'Phone number or Alphanumeric SenderID')
            ->addArgument('to', InputArgument::REQUIRED, 'Phone number to contact')
            ->addArgument('message', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Message to send');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageManager->sendMessage(
            $input->getArgument('from'),
            $input->getArgument('to'),
            implode(' ', $input->getArgument('message')),
        );

        return Command::SUCCESS;
    }
}
