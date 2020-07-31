<?php

namespace App\Command;

use App\Client\GuzzleClient;
use Exception;
use IM\Fabric\Package\Security\TokenGenerator\AuthenticatorInterface;
use League\Flysystem\Filesystem;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ContentDuplication extends Command
{
    protected static $defaultName = 'content:duplication';

    private const ALLOWED = ['time', 'meta', 'settings', 'card', 'userRatings', 'author', 'sponsor', 'servings', 'primaryCategory'];

    private const SUPPORTED_POST_ENDPOINTS_WITH_LIMITS = [
        'editorial_lists' => '10',
        'glossaries' => '100',
        'how_tos' => '100',
        'plants' => '100',
        'posts' => '100',
        'recipes' => '100',
        'reviews' => '100',
        'venues' => '100'
    ];

    private const REPORT_DIR = 'reports';

    private const URL_FORMAT = '%sv1/%s.jsonld?client=bbcgoodfood&limit=%d&page=%d';

    private const HIGH_PRIORITY = ['endSummary', 'ingredients', 'method', 'description'];

    /**
     * @var string
     */
    private $contentBaseUrl;

    /**
     * @var GuzzleClient
     */
    private $client;

    /**
     * @var AuthenticatorInterface
     */
    private $authenticator;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        string $contentBaseUrl,
        GuzzleClient $client,
        AuthenticatorInterface $authenticator,
        FileSystem $filesystem
    )
    {
        parent::__construct();
        $this->contentBaseUrl = $contentBaseUrl;
        $this->client = $client;
        $this->authenticator = $authenticator;
        $this->filesystem = $filesystem;
    }

    protected function configure(): void
    {
        $this->setDescription('Duplication check')
            ->setHelp('Command for scanning the content api looking for entries with duplicated fields')
            ->addOption(
                'rawfile',
                'r',
                InputOption::VALUE_OPTIONAL,
                'The file you wish to analyse'
            )
            ->addOption(
                'types',
                't',
                InputOption::VALUE_OPTIONAL,
                'The post types you wish to scan'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $endpoints = $input->getOption('types') ?
            array_intersect(
                array_keys(self::SUPPORTED_POST_ENDPOINTS_WITH_LIMITS),
                explode(',',$input->getOption('types'))
            ) :
            array_keys(self::SUPPORTED_POST_ENDPOINTS_WITH_LIMITS);

        if (empty($endpoints)){
            $io->error('You have passed through an invalid entity type');
            return 0;
        }

        $filenameBase = 'Report ' . date('Y-m-d H:i:s');

        $rawFile = $input->getOption('rawfile');

        if (!$rawFile) {
            $rawFile = $filenameBase . ' raw';

            $this->generateRaw($rawFile, $io, $endpoints);
        }

        $filenameAnalysis = $filenameBase . ' analysis';
        $this->runAnalysis($rawFile, $filenameAnalysis);

        return 1;
    }

    private function clearOutIds(array $data): array
    {
        unset($data['@id'], $data['id']);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->clearOutIds($value);
            }
        }
        return $data;
    }

    private function generateRaw($filename, SymfonyStyle $io, $endpoints): void
    {
        $this->createFile($filename);

        foreach($endpoints as $endpoint) {
            $io->text ('Processing endpoint: ' . $endpoint);

            $page = 1;
            $lastPage = 1;

            while ($page <= $lastPage) {
                $result = $this->client->get(
                    sprintf(
                        self::URL_FORMAT,
                        $this->contentBaseUrl,
                        $endpoint,
                        self::SUPPORTED_POST_ENDPOINTS_WITH_LIMITS[$endpoint],
                        $page
                    ),
                    $this->authenticator);

                if (!$result instanceof ResponseInterface) {
                    continue;
                }

                $responseBody = json_decode($result->getBody()->getContents(), true);

                if (empty($responseBody['hydra:member'])) {
                    break;
                }

                if ($page === 1) {
                    $lastPage = 1;
                    if (isset($responseBody['hydra:view']['hydra:last'])) {
                        preg_match('/(?<=page=)([\d]+)/', $responseBody['hydra:view']['hydra:last'], $matches);
                        $lastPage = $matches[0];
                        $lastPage = 2;
                    }
                    $io->progressStart($lastPage);
                }

                $documents = $this->clearOutIds($responseBody['hydra:member']);

                $errors = [];
                foreach ($documents as $document) {
                    foreach ($document as $attribute => $value) {
                        if (in_array($attribute, self::ALLOWED) || !is_array($value)) {
                            continue;
                        }

                        foreach ($value as $key => $item) {
                            $duplicates = array_filter($value, function ($element) use ($item) {
                                return $item == $element;
                            });

                            if (count($duplicates) > 1) {
                                $errors[$document['clientId']]['attributes'][] = $attribute;
                                $errors[$document['clientId']]['endpoint'] = $endpoint;
                                break;
                            }
                        }
                    }
                }

                $this->updateFile($filename, $errors);

                $page++;

                $io->progressAdvance();
            }

            try {
                $io->progressFinish();
            }catch (Exception $e){

            }
        }
    }

    private function runAnalysis(string $rawFile, string $analysisFile): void
    {
        $this->createFile($analysisFile);

        $file = $this->filesystem->get(self::REPORT_DIR . '/' . $rawFile);
        $fileContents = json_decode($file->read(), true);

        $output['aggregate']['total'] = count($fileContents);

        $highPriorityCount = 0;
        $lowPriorityCount = 0;
        $typeCount = [];

        foreach ($fileContents as $clientId => $details) {
            foreach($details['attributes'] as $attribute){
                $typeCount[$attribute] = ($typeCount[$attribute] ?? 0) + 1;
            }
            if (array_intersect($details['attributes'], self::HIGH_PRIORITY)) {
                $output['priority:high'][$details['entity']][$clientId] = $details['attributes'];
                $highPriorityCount++;
                continue;
            }
            $output['priority:low'][$details['endpoint']][$clientId] = $details['attributes'];
            $lowPriorityCount++;
        }

        $output['aggregate']['high'] = $highPriorityCount;
        $output['aggregate']['low'] = $lowPriorityCount;
        $output['aggregate']['attributes'] = $typeCount;

        $this->filesystem->put(self::REPORT_DIR . '/' . $analysisFile, json_encode($output, JSON_PRETTY_PRINT));
    }


    private function createFile(string $filename): void
    {
        $this->filesystem->createDir(self::REPORT_DIR);
        $this->filesystem->put(self::REPORT_DIR . '/' . $filename, '{}');
    }

    private function updateFile(string $filename, array $errors): void
    {
        $file = $this->filesystem->get(self::REPORT_DIR . '/' . $filename);

        $fileContents = json_decode($file->read(), true);
        $fileContents += $errors;

        $this->filesystem->put(self::REPORT_DIR . '/' . $filename, json_encode($fileContents, JSON_PRETTY_PRINT));
    }


}