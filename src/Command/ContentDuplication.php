<?php

namespace App\Command;

use App\Client\GuzzleClient;
use App\Duplication\DuplicationCheckerInterface;
use IM\Fabric\Package\Security\TokenGenerator\AuthenticatorInterface;
use League\Flysystem\Filesystem;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ContentDuplication extends Command
{
    protected static $defaultName = 'content:duplication';

    private const ALLOWED = ['time', 'meta', 'settings', 'card', 'userRatings', 'author', 'sponsor', 'servings'];

    private const REPORT_DIR = 'reports';

    private const URL = 'v1/recipes.jsonld?client=bbcgoodfood&limit=100&page=';

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

    private const TEST_FILE_PATH = __DIR__ . '/../../tests/assets/Duplication/';

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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filenameBase = 'Report ' . date('Y-m-d H:i:s');

        $rawFile = $input->getOption('rawfile');

        if (!$rawFile) {
            $rawFile = $filenameBase . ' raw';

            $this->generateRaw($rawFile, $output);
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

    private function generateRaw($filename, $output): void
    {
        $progressBar = new ProgressBar($output);

        $this->createFile($filename);

        $page = 1;
        $lastPage = 1;

        while ($page <= $lastPage) {
            $result = $this->client->get($this->contentBaseUrl . self::URL . $page, $this->authenticator);

            if (!$result instanceof ResponseInterface) {
                continue;
            }

            $responseBody = json_decode($result->getBody()->getContents(), true);

            if (empty($responseBody['hydra:member'])) {
                break;
            }

            if ($page === 1) {
                preg_match('/(?<=page=)([\d]+)/', $responseBody['hydra:view']['hydra:last'], $matches);
                $lastPage = $matches[0];

                $progressBar->setMaxSteps($lastPage);
                $progressBar->start();
            }

            $recipes = $this->clearOutIds($responseBody['hydra:member']);

            $errors = [];
            foreach ($recipes as $recipe) {
                foreach ($recipe as $attribute => $value) {
                    if (in_array($attribute, self::ALLOWED) || !is_array($value)) {
                        continue;
                    }

                    foreach ($value as $key => $item) {
                        $duplicates = array_filter($value, function ($element) use ($item) {
                            return $item == $element;
                        });

                        if (count($duplicates) > 1) {
                            $errors[$recipe['clientId']][] = $attribute;
                            break;
                        }
                    }
                }
            }

            $this->updateFile($filename, $errors);

            $page++;
            $progressBar->advance();
        }

        $progressBar->finish();
    }

    private function runAnalysis(string $rawFile, string $analysisFile): void
    {
        $this->createFile($analysisFile);

        $file = $this->filesystem->get(self::REPORT_DIR . '/' . $rawFile);
        $fileContents = json_decode($file->read(), true);

        $output['total'] = 'Total recipes with errors: ' . count($fileContents);
        $output['total:priority:high'] = 0;
        $output['total:priority:low'] = 0;

        foreach ($fileContents as $clientId => $attributes) {
            if (array_intersect($attributes, self::HIGH_PRIORITY)) {
                $output['priority:high'][$clientId] = $attributes;
                continue;
            }
            $output['priority:low'][$clientId] = $attributes;
        }

        $output['total:priority:high'] = count($output['priority:high']);
        $output['total:priority:low'] = count($output['priority:low']);

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