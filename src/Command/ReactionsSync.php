<?php

namespace App\Command;

use App\Client\GuzzleClient;
use IM\Fabric\Package\Security\TokenGenerator\AuthenticatorInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReactionsSync extends Command
{
    protected static $defaultName = 'reactions:sync';
    /**
     * @var string
     */
    private $reactionsBaseUrl;
    /**
     * @var GuzzleClient
     */
    private $client;
    /**
     * @var AuthenticatorInterface
     */
    private $authenticator;

    public function __construct(string $reactionsBaseUrl, GuzzleClient $client, AuthenticatorInterface $authenticator)
    {
        parent::__construct();
        $this->reactionsBaseUrl = $reactionsBaseUrl;
        $this->client = $client;
        $this->authenticator = $authenticator;
    }

    protected function configure(): void
    {
        $this->setDescription('Reactions Sync')
            ->setHelp('Command for running sync against reactions')
            ->addArgument(
                'service',
                InputArgument::REQUIRED,
                'The service you wish to update'
            )->addArgument(
                'limit',
                InputArgument::OPTIONAL,
                'How many aggregate ratings to push per attempt',
                30
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = $input->getArgument('service');
        $statusCode = 0;
        $limit = $input->getArgument('limit');
        $offset = 0;
        while($statusCode < 400){
            printf("Attempt to update %s with %d ratings at offset %d\n", $service, $limit, $offset);
            $result = $this->client->get($this->reactionsBaseUrl . 'aggregateRating/push?limit='.$limit.'&offset='.$offset.'&service='.$service, $this->authenticator);
            if (!$result instanceof ResponseInterface){
                $statusCode = 500;
                continue;
            }
            $statusCode = $result->getStatusCode();
            echo($result->getBody()->getContents(). "\n");
            $offset += $limit;
        }
        return 1;
    }
}