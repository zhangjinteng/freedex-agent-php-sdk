<?php
declare(strict_types=1);

namespace Freedex\Agent;

use Freedex\Agent\Exception\AgentSdkException;
use Freedex\Agent\Http\CurlHttpTransport;
use Freedex\Agent\Http\HttpTransportInterface;

class AgentConfig
{
    /** @var string */
    private $baseUrl;
    /** @var string */
    private $agentCode;
    /** @var string */
    private $apiSecret;
    /** @var string */
    private $defaultCurrency;
    /** @var float */
    private $timeoutSeconds;
    /** @var HttpTransportInterface */
    private $transport;
    /** @var callable */
    private $nonceGenerator;
    /** @var callable */
    private $timestampProvider;

    public function __construct(
        string $baseUrl,
        string $agentCode,
        string $apiSecret,
        string $defaultCurrency = 'USDT',
        float $timeoutSeconds = 10.0,
        ?HttpTransportInterface $transport = null,
        ?callable $nonceGenerator = null,
        ?callable $timestampProvider = null
    ) {
        $this->baseUrl = $this->normalizeBaseUrl($this->requireNotBlank($baseUrl, 'baseUrl is required'));
        $this->agentCode = $this->requireNotBlank($agentCode, 'agentCode is required');
        $this->apiSecret = $this->requireNotBlank($apiSecret, 'apiSecret is required');
        $this->defaultCurrency = trim($defaultCurrency);
        $this->timeoutSeconds = $timeoutSeconds;
        $this->transport = $transport ?: new CurlHttpTransport();
        $this->nonceGenerator = $nonceGenerator ?: function (): string {
            return bin2hex(random_bytes(16));
        };
        $this->timestampProvider = $timestampProvider ?: function (): int {
            return time();
        };
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAgentCode(): string
    {
        return $this->agentCode;
    }

    public function getApiSecret(): string
    {
        return $this->apiSecret;
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    public function getTimeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    public function getTransport(): HttpTransportInterface
    {
        return $this->transport;
    }

    public function newNonce(): string
    {
        return (string) call_user_func($this->nonceGenerator);
    }

    public function timestamp(): int
    {
        return (int) call_user_func($this->timestampProvider);
    }

    private function requireNotBlank(string $value, string $message): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new AgentSdkException($message);
        }
        return $trimmed;
    }

    private function normalizeBaseUrl(string $value): string
    {
        return rtrim($value, '/');
    }
}
