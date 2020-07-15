<?php

namespace App\Client;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use IM\Fabric\Package\Security\TokenGenerator\AuthenticatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class GuzzleClient
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param string $url
     * @param AuthenticatorInterface $authenticator
     * @return ResponseInterface|null
     */
    public function get(string $url, AuthenticatorInterface $authenticator): ?ResponseInterface
    {
        try {
            return $this->client->request(Request::METHOD_GET, $url, [
                'headers' => $this->getRequestHeaders($authenticator)
            ]);
        } catch (ServerException $e) {
            return $e->getResponse();
        } catch (ClientException $e) {
            return $e->getResponse();
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());
            return null;
        }
    }

    /**
     * @param AuthenticatorInterface $authenticator
     * @return array
     * @throws Exception
     */
    private function getRequestHeaders(AuthenticatorInterface $authenticator): array
    {
        return ['Authorization' => 'Bearer ' . $authenticator->getToken()];
    }
}
