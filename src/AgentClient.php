<?php
declare(strict_types=1);

namespace Freedex\Agent;

use Freedex\Agent\Exception\AgentApiException;
use Freedex\Agent\Exception\AgentSdkException;
use Freedex\Agent\Model\AgentResponse;
use Freedex\Agent\Model\BindRequest;
use Freedex\Agent\Model\BindResponse;
use Freedex\Agent\Model\CreateEntryUrlRequest;
use Freedex\Agent\Model\CreateEntryUrlResponse;
use Freedex\Agent\Model\ListOrdersRequest;
use Freedex\Agent\Model\ListOrdersResponse;
use Freedex\Agent\Model\QueryAccountRequest;
use Freedex\Agent\Model\QueryAccountResponse;
use Freedex\Agent\Model\QueryOrderRequest;
use Freedex\Agent\Model\QueryOrderResponse;
use Freedex\Agent\Model\QueryUserAssetsRequest;
use Freedex\Agent\Model\QueryUserAssetsResponse;
use Freedex\Agent\Model\ReverseOrderRequest;
use Freedex\Agent\Model\ReverseOrderResponse;
use Freedex\Agent\Model\TransferAllOutRequest;
use Freedex\Agent\Model\TransferAllOutResponse;
use Freedex\Agent\Model\TransferRequest;
use Freedex\Agent\Model\TransferResponse;
use Freedex\Agent\Model\VersionResponse;

class AgentClient
{
    /** @var AgentConfig */
    private $config;

    /** @var string */
    private $lastSignPayload = '';

    /** @var string */
    private $lastRequestUrl = '';

    /** @var string */
    private $lastRequestBody = '';

    /** @var int */
    private $lastHttpStatus = 200;

    public function __construct(AgentConfig $config)
    {
        $this->config = $config;
    }

    public function getLastSignPayload(): string
    {
        return $this->lastSignPayload;
    }

    public function getLastRequestUrl(): string
    {
        return $this->lastRequestUrl;
    }

    public function getLastRequestBody(): string
    {
        return $this->lastRequestBody;
    }

    public function getLastHttpStatus(): int
    {
        return $this->lastHttpStatus;
    }

    public function bind(BindRequest $request): BindResponse
    {
        return $this->postSigned('/v1/agent/bind', $request->toArray(), BindResponse::class);
    }

    public function transfer(TransferRequest $request): TransferResponse
    {
        return $this->postSigned('/v1/agent/transfer', $request->toArray(), TransferResponse::class);
    }

    public function transferAllOut(TransferAllOutRequest $request): TransferAllOutResponse
    {
        return $this->postSigned('/v1/agent/transfer-all-out', $request->toArray(), TransferAllOutResponse::class);
    }

    public function reverse(ReverseOrderRequest $request): ReverseOrderResponse
    {
        return $this->postSigned('/v1/agent/reverse', $request->toArray(), ReverseOrderResponse::class);
    }

    public function queryOrder(QueryOrderRequest $request): QueryOrderResponse
    {
        return $this->postSigned('/v1/agent/query-order', $request->toArray(), QueryOrderResponse::class);
    }

    public function listOrders(ListOrdersRequest $request): ListOrdersResponse
    {
        return $this->postSigned('/v1/agent/list-orders', $request->toArray(), ListOrdersResponse::class);
    }

    public function queryAccount(QueryAccountRequest $request): QueryAccountResponse
    {
        return $this->postSigned('/v1/agent/query-account', $request->toArray(), QueryAccountResponse::class);
    }

    public function queryUserAssets(QueryUserAssetsRequest $request): QueryUserAssetsResponse
    {
        return $this->postSigned('/v1/agent/query-user-assets', $request->toArray(), QueryUserAssetsResponse::class);
    }

    public function createEntryUrl(CreateEntryUrlRequest $request): CreateEntryUrlResponse
    {
        return $this->postSigned('/v1/agent/create-entry-url', $request->toArray(), CreateEntryUrlResponse::class);
    }

    public function version(): VersionResponse
    {
        return $this->send('GET', '/version', [], null, VersionResponse::class);
    }

    /**
     * @param array<string,mixed> $body
     * @template T of object
     * @param class-string<T> $responseClass
     * @return T
     */
    private function postSigned(string $path, array $body, string $responseClass)
    {
        $this->lastRequestUrl = $this->config->getBaseUrl() . $path;
        $body = $this->scrubEmptyValues($body);
        $body['agentCode'] = $this->config->getAgentCode();
        $body['timestamp'] = $this->config->timestamp();
        $body['nonce'] = $this->config->newNonce();
        $this->applyDefaultCurrency($path, $body);
        $this->lastSignPayload = AgentSigner::buildSignPayload($body);
        $body['sign'] = AgentSigner::signParams($body, $this->config->getApiSecret());

        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new AgentSdkException('failed to serialize agent api request');
        }
        $this->lastRequestBody = $json;
        return $this->send('POST', $path, ['Content-Type' => 'application/json'], $json, $responseClass);
    }

    /**
     * @template T of object
     * @param array<string,string> $headers
     * @param class-string<T> $responseClass
     * @return T
     */
    private function send(string $method, string $path, array $headers, ?string $body, string $responseClass)
    {
        $resp = $this->config->getTransport()->send(
            $method,
            $this->config->getBaseUrl() . $path,
            $headers,
            $body,
            $this->config->getTimeoutSeconds()
        );

        $this->lastHttpStatus = $resp->getStatusCode();

        // if (function_exists('dd')) {
        //     dd([
        //         'request_url' => $this->config->getBaseUrl() . $path,
        //         'request_body' => json_decode($body ?? '', true) ?: $body,
        //         'request_headers' => $headers,
        //         'response_body' => json_decode($resp->getBody(), true) ?: $resp->getBody(),
        //         'response_status' => $resp->getStatusCode()
        //     ]);
        // } else {
        //     var_dump([
        //         'request_url' => $this->config->getBaseUrl() . $path,
        //         'request_body' => json_decode($body ?? '', true) ?: $body,
        //         'request_headers' => $headers,
        //         'response_body' => json_decode($resp->getBody(), true) ?: $resp->getBody(),
        //         'response_status' => $resp->getStatusCode()
        //     ]);
        //     exit(1);
        // }

        if ($resp->getStatusCode() < 200 || $resp->getStatusCode() >= 300) {
            throw new AgentApiException('agent api returned http status ' . $resp->getStatusCode(), $resp->getStatusCode(), 0, $resp->getBody());
        }

        $data = json_decode($resp->getBody(), true);
        if (!is_array($data)) {
            throw new AgentSdkException('failed to parse agent api response');
        }
        $result = $this->hydrate($responseClass, $data);
        if ($result instanceof AgentResponse && $result->code !== 0) {
            throw new AgentApiException($result->message . ' (code=' . $result->code . ')', $resp->getStatusCode(), $result->code, $resp->getBody());
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $body
     */
    private function applyDefaultCurrency(string $path, array &$body): void
    {
        if ($this->config->getDefaultCurrency() === '') {
            return;
        }
        if (!in_array($path, ['/v1/agent/transfer', '/v1/agent/transfer-all-out', '/v1/agent/query-account'], true)) {
            return;
        }
        if (!isset($body['currency']) || trim((string) $body['currency']) === '') {
            $body['currency'] = $this->config->getDefaultCurrency();
        }
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function scrubEmptyValues(array $body): array
    {
        foreach ($body as $key => $value) {
            if ($value === null || $value === '') {
                unset($body[$key]);
            }
        }
        return $body;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string,mixed> $data
     * @return T
     */
    private function hydrate(string $class, array $data)
    {
        $obj = new $class();
        foreach ($data as $key => $value) {
            if (property_exists($obj, $key)) {
                $obj->{$key} = $value;
            }
        }
        return $obj;
    }
}
