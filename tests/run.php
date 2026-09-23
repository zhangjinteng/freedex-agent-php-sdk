<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Freedex\\Agent\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use Freedex\Agent\AgentClient;
use Freedex\Agent\AgentConfig;
use Freedex\Agent\AgentSigner;
use Freedex\Agent\Exception\AgentApiException;
use Freedex\Agent\Exception\AgentSdkException;
use Freedex\Agent\Http\HttpResponse;
use Freedex\Agent\Http\HttpTransportInterface;
use Freedex\Agent\Model\BindRequest;
use Freedex\Agent\Model\CreateEntryUrlRequest;
use Freedex\Agent\Model\Direction;
use Freedex\Agent\Model\QueryOrderRequest;
use Freedex\Agent\Model\QueryUserAssetsRequest;
use Freedex\Agent\Model\TransferAllOutRequest;
use Freedex\Agent\Model\TransferRequest;
use Freedex\Agent\WebhookVerifier;

function assertSameValue($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertTrueValue($actual, string $message = ''): void
{
    if ($actual !== true) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'expected true');
    }
}

function assertStringContainsValue(string $needle, string $haystack, string $message = ''): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'missing ' . $needle . ' in ' . $haystack);
    }
}

function assertThrows(callable $fn, string $class): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new RuntimeException('expected exception ' . $class . ', got ' . get_class($e), 0, $e);
    }
    throw new RuntimeException('expected exception ' . $class . ', got none');
}

final class CapturingTransport implements HttpTransportInterface
{
    public $calls = 0;
    /** @var int */
    private $status;
    /** @var string */
    private $responseBody;
    /** @var string */
    public $lastMethod = '';
    /** @var string */
    public $lastUrl = '';
    /** @var array<string,string> */
    public $lastHeaders = [];
    /** @var string */
    public $lastBody = '';

    public function __construct(int $status, string $responseBody)
    {
        $this->status = $status;
        $this->responseBody = $responseBody;
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): HttpResponse
    {
        ++$this->calls;
        $this->lastMethod = $method;
        $this->lastUrl = $url;
        $this->lastHeaders = $headers;
        $this->lastBody = $body ?? '';
        return new HttpResponse($this->status, $this->responseBody);
    }
}

$tests = [];

$tests['signer builds sorted payload and skips empty values'] = function (): void {
    $params = [
        'sign' => 'ignored',
        'nonce' => 'nonce-1',
        'amount' => '10.00',
        'currency' => '',
        'timestamp' => 1713024000,
        'agentCode' => 'AGENT001',
    ];

    assertSameValue(
        'agentCode=AGENT001&amount=10.00&nonce=nonce-1&timestamp=1713024000',
        AgentSigner::buildSignPayload($params)
    );
    assertSameValue(
        'dcb87a1b3133116a34df461f3bc61824b657ea42be4342ddf2335461df40d383',
        AgentSigner::signParams($params, 'secret')
    );
};

$tests['webhook verifier accepts valid signature and builds idempotency key'] = function (): void {
    $body = '{"orderType":"transfer","orderId":123,"targetStatus":"SUCCESS"}';
    $signature = AgentSigner::signWebhook('secret', '1713024000', 'nonce-1', $body);

    assertTrueValue(WebhookVerifier::verify('secret', '1713024000', 'nonce-1', $body, $signature));
    assertSameValue('transfer:123:SUCCESS', WebhookVerifier::idempotencyKey($body));
};

$tests['config validates required fields'] = function (): void {
    assertThrows(function (): void {
        new AgentConfig('', 'AGENT001', 'secret');
    }, AgentSdkException::class);
};

$tests['client injects sign fields and sends transfer'] = function (): void {
    $transport = new CapturingTransport(200, '{"code":0,"message":"success","orderStatus":"PROCESSING"}');
    $config = new AgentConfig(
        'http://agent.test',
        'AGENT001',
        'secret',
        'USDT',
        10.0,
        $transport,
        function (): string {
            return 'nonce-1';
        },
        function (): int {
            return 1713024000;
        }
    );
    $client = new AgentClient($config);

    $resp = $client->transfer(TransferRequest::fixed('A-1', 'u-1', Direction::IN, 'USDT', '10.00'));

    assertSameValue('PROCESSING', $resp->orderStatus);
    assertSameValue('POST', $transport->lastMethod);
    assertSameValue('http://agent.test/v1/agent/transfer', $transport->lastUrl);
    assertSameValue('application/json', $transport->lastHeaders['Content-Type']);
    assertStringContainsValue('"agentCode":"AGENT001"', $transport->lastBody);
    assertStringContainsValue('"timestamp":1713024000', $transport->lastBody);
    assertStringContainsValue('"nonce":"nonce-1"', $transport->lastBody);
    assertStringContainsValue('"sign":"', $transport->lastBody);
    assertSameValue($transport->lastUrl, $client->getLastRequestUrl());
    assertSameValue($transport->lastBody, $client->getLastRequestBody());
    assertSameValue(200, $client->getLastHttpStatus());
    assertStringContainsValue('agentCode=AGENT001', $client->getLastSignPayload());
};

$tests['client hydrates simulated user flag from bind response'] = function (): void {
    $transport = new CapturingTransport(200, '{"code":0,"message":"success","platformUserId":"1188041528","bindStatus":"BOUND","isSimulatedUser":true}');
    $config = new AgentConfig(
        'http://agent.test',
        'AGENT001',
        'secret',
        'USDT',
        10.0,
        $transport,
        function (): string {
            return 'nonce-1';
        },
        function (): int {
            return 1713024000;
        }
    );
    $client = new AgentClient($config);

    $resp = $client->bind(BindRequest::of('u-1')->withUsername('Alice'));

    assertTrueValue($resp->isSimulatedUser);
    assertStringContainsValue('"username":"Alice"', $transport->lastBody);
};

$tests['client hydrates simulated user flag from query user assets response'] = function (): void {
    $transport = new CapturingTransport(200, '{"code":0,"message":"success","platformUserId":"1188041528","walletBalance":"10","availableBalance":"9","isSimulatedUser":true}');
    $config = new AgentConfig(
        'http://agent.test',
        'AGENT001',
        'secret',
        'USDT',
        10.0,
        $transport,
        function (): string {
            return 'nonce-1';
        },
        function (): int {
            return 1713024000;
        }
    );
    $client = new AgentClient($config);

    $resp = $client->queryUserAssets(QueryUserAssetsRequest::of('1188041528'));

    assertTrueValue($resp->isSimulatedUser);
};

$tests['client sends transfer by platform user id and hydrates both identifiers'] = function (): void {
    $transport = new CapturingTransport(200, '{"code":0,"message":"success","orderNo":"A-3","orderStatus":"SUCCESS","agentUserId":"u-3","platformUserId":"1188041528"}');
    $config = new AgentConfig(
        'http://agent.test',
        'AGENT001',
        'secret',
        'USDT',
        10.0,
        $transport,
        function (): string {
            return 'nonce-1';
        },
        function (): int {
            return 1713024000;
        }
    );
    $client = new AgentClient($config);

    $resp = $client->transfer(TransferRequest::fixedByPlatformUserId('A-3', '1188041528', Direction::OUT, 'USDT', '2.50'));

    assertSameValue('u-3', $resp->agentUserId);
    assertSameValue('1188041528', $resp->platformUserId);
    assertStringContainsValue('"platformUserId":"1188041528"', $transport->lastBody);
    assertTrueValue(strpos($transport->lastBody, '"agentUserId"') === false);
};

$tests['client sends entry url return url'] = function (): void {
    $transport = new CapturingTransport(200, '{"code":0,"message":"success","webUrl":"http://app.test/agent-entry?ticket=abc","expireAt":1777000060}');
    $config = new AgentConfig(
        'http://agent.test',
        'AGENT001',
        'secret',
        'USDT',
        10.0,
        $transport,
        function (): string {
            return 'nonce-1';
        },
        function (): int {
            return 1713024000;
        }
    );
    $client = new AgentClient($config);

    $resp = $client->createEntryUrl(
        CreateEntryUrlRequest::of('u-1')
            ->withRedirectPath('/trade/BTCUSDT')
            ->withReturnUrl('https://partner.example/return#markets')
    );

    assertSameValue('http://app.test/agent-entry?ticket=abc', $resp->webUrl);
    assertSameValue(1777000060, $resp->expireAt);
    assertSameValue('POST', $transport->lastMethod);
    assertSameValue('http://agent.test/v1/agent/create-entry-url', $transport->lastUrl);
    assertStringContainsValue('"agentUserId":"u-1"', $transport->lastBody);
    assertStringContainsValue('"redirectPath":"/trade/BTCUSDT"', $transport->lastBody);
    assertStringContainsValue('"returnUrl":"https://partner.example/return#markets"', $transport->lastBody);
};

$tests['client sends all-out by platform user id and hydrates both identifiers'] = function (): void {
    $transport = new CapturingTransport(200, '{"code":0,"message":"success","orderNo":"A-4","orderStatus":"SUCCESS","amount":"8.50","agentUserId":"u-4","platformUserId":"1188041529"}');
    $config = new AgentConfig(
        'http://agent.test',
        'AGENT001',
        'secret',
        'USDT',
        10.0,
        $transport,
        function (): string {
            return 'nonce-1';
        },
        function (): int {
            return 1713024000;
        }
    );
    $client = new AgentClient($config);

    $resp = $client->transferAllOut(TransferAllOutRequest::byPlatformUserId('A-4', '1188041529', 'USDT'));

    assertSameValue('u-4', $resp->agentUserId);
    assertSameValue('1188041529', $resp->platformUserId);
    assertStringContainsValue('"platformUserId":"1188041529"', $transport->lastBody);
    assertTrueValue(strpos($transport->lastBody, '"agentUserId"') === false);
};

$tests['client throws business exception for non-zero code'] = function (): void {
    $transport = new CapturingTransport(200, '{"code":6101,"message":"order not found"}');
    $config = new AgentConfig('http://agent.test', 'AGENT001', 'secret', 'USDT', 10.0, $transport);
    $client = new AgentClient($config);

    $e = assertThrows(function () use ($client): void {
        $client->queryOrder(QueryOrderRequest::of('missing', 'TRANSFER_IN'));
    }, AgentApiException::class);

    assertSameValue(6101, $e->getCodeValue());
};

require __DIR__ . '/wallet.php';

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo '[PASS] ' . $name . PHP_EOL;
}

echo 'Tests run: ' . $passed . ', Failures: 0' . PHP_EOL;
