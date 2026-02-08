<?php

namespace App\Command;

use App\Manager\TwilioCallManager;
use App\Manager\TwilioMessageManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'twilio:price',
    description: 'Fetch missing SMS and call prices from Twilio',
)]
class TwilioPriceCommand extends Command
{
    public function __construct(
        private readonly TwilioMessageManager $messageManager,
        private readonly TwilioCallManager $callManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('retry', InputArgument::OPTIONAL, 'Number of retries on Twilio before skipping', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageManager->fetchPrices(
            $input->getArgument('retry'),
        );

        $this->callManager->fetchPrices(
            $input->getArgument('retry'),
        );

        return Command::SUCCESS;
    }
}
