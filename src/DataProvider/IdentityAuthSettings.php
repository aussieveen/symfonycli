<?php

namespace App\DataProvider;

use IM\Fabric\Package\Security\TokenGenerator\DataProvider\AuthSettingsProviderInterface;

class IdentityAuthSettings implements AuthSettingsProviderInterface
{
    /**
     * @var string
     */
    private $provider;

    /**
     * @var string
     */
    private $identityBaseUrl;

    /**
     * @var string
     */
    private $identityClientId;

    /**
     * @var string
     */
    private $identityClientSecret;

//    public const SCOPES = ['im-platform-reactions-api:all:manage', 'WebUserAccountApi'];
    public const SCOPES = ['WebUserAccountApi'];

    public function __construct(
        string $provider,
        string $identityBaseUrl = null,
        string $identityClientId = null,
        string $identityClientSecret = null
    ) {
        $this->provider = $provider;
        $this->identityBaseUrl = $identityBaseUrl;
        $this->identityClientId = $identityClientId;
        $this->identityClientSecret = $identityClientSecret;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getScopes(): array
    {
        return self::SCOPES;
    }

    public function getIdentityBaseUrl(): ?string
    {
        return $this->identityBaseUrl;
    }

    public function getIdentityClientId(): ?string
    {
        return $this->identityClientId;
    }

    public function getIdentityClientSecret(): ?string
    {
        return $this->identityClientSecret;
    }

    public function asArray(): array
    {
        return [
            'provider' => $this->provider,
            'scopes' => $this->getScopes(),
            'identityBaseUrl' => $this->identityBaseUrl,
            'identityClientId' => $this->identityClientId,
            'identityClientSecret' => $this->identityClientSecret
        ];
    }
}
