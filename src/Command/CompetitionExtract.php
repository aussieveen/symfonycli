<?php

namespace App\Command;

use App\Client\GuzzleClient;
use IM\Fabric\Package\Security\TokenGenerator\AuthenticatorInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompetitionExtract extends Command
{
    protected static $defaultName = 'competitions:extract';
    /**
     * @var string
     */
    private $identityBaseUrl;
    /**
     * @var GuzzleClient
     */
    private $client;
    /**
     * @var AuthenticatorInterface
     */
    private $authenticator;

    public function __construct(string $identityBaseUrl, GuzzleClient $client, AuthenticatorInterface $authenticator)
    {
        parent::__construct();
        $this->identityBaseUrl = $identityBaseUrl;
        $this->client = $client;
        $this->authenticator = $authenticator;

    }

    protected function configure(): void
    {
        $this->setDescription('Competition extraction')
            ->setHelp('Command for extracting data out of competition sql files.')
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'The directory the mysql extracts are stored',
                'extracts'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = './' . $input->getArgument('directory') . '/';

        $files = scandir($path);
        foreach ($files as $file) {

            $correct = [];
            $optedin = [];

            $fileContents = file_get_contents($path . $file);
            if (!$fileContents) {
                continue;
            }

            preg_match('/(\d)*/', $file, $matches);
            $id = $matches[0];
            $lines = explode("\n", $fileContents);
            unset($lines[0]);

            $correct = $optedin = [];

            foreach($lines as $line){
                $array = unserialize($line);

                if ($array['correctAnswer']){
                    $correct[] = $array['guid'] . "|" . $id;
                }
                if ($array['optIn'] !== false){
                    $optedin[] = $array['guid'];
                }
            }

            $correct = implode("\n", $correct);
            file_put_contents($id . 'correct.txt', $correct);

            
            $result = $this->client->get($this->identityBaseUrl . '/WebUserAccountApi/users/usersinfo?userIds=' . implode(',', $optedin), $this->authenticator);

            if ($result === null){
                continue;
            }

            $results = json_decode($result->getBody()->getContents(), true);
            $optedInWithDetails[] = 'competition id,email,given_name,family_name,address1,address2,postcode,city,county,phone_number';
            foreach($results['result']['users'] as $user){
                $optedInWithDetails[] = implode(',', array_merge([$id], $this->getUserDetails($user)));
            }

            $optedInWithDetails = implode("\n", $optedInWithDetails);
            file_put_contents($id . 'optedIn.txt', $optedInWithDetails);

        }
        return 1;
    }

    private function getUserDetails($userDetails): array
    {
        $details = [$userDetails['userId']];
        $requiredFields = ['email', 'given_name', 'family_name', 'address1','address2','postcode','city','county', 'phone_number'];
        foreach($requiredFields as $field){
            foreach($userDetails['userSettings'] as $setting){
                if ($field === $setting['type']){
                    $details[] = $setting['value'];
                    continue;
                }
            }
        }
        return $details;
    }
}

