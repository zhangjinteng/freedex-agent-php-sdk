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
use Freedex\Agent\Model\Direction;
use Freedex\Agent\Model\QueryOrderRequest;
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
};

// 测试：当接口返回非 0 业务错误码时，客户端应正确抛出业务异常 (AgentApiException)
// 1. 将该测试函数注册到 $tests 数组中，键名为描述性的测试名称，返回类型为 void
$tests['client throws business exception for non-zero code'] = function (): void {
    // 2. 实例化模拟网络传输类 CapturingTransport，模拟 HTTP 状态码为 200 OK 且返回 code 为 6101 (订单未找到) 的 JSON 字符串
    $transport = new CapturingTransport(200, '{"code":6101,"message":"order not found"}');
    // 3. 实例化 AgentConfig 配置类，传入代理商基础 URL、代码、秘钥、默认币种、超时时间 (10秒) 和模拟网络传输对象
    $config = new AgentConfig('http://agent.test', 'AGENT001', 'secret', 'USDT', 10.0, $transport);
    // 4. 使用上述配置类实例化 Agent 客户端核心操作对象 AgentClient
    $client = new AgentClient($config);

    // 5. 调用 assertThrows 辅助测试函数，验证内部闭包执行时是否抛出了预期的 AgentApiException 业务异常类
    $e = assertThrows(function () use ($client): void {
        // 6. 调用客户端的订单查询接口 queryOrder，传入不存在的订单号 'missing' 以及划转类型 'TRANSFER_IN'，预期此时因模拟响应报错而抛出异常
        $client->queryOrder(QueryOrderRequest::of('missing', 'TRANSFER_IN'));
    }, AgentApiException::class);

    // 7. 调用 assertSameValue 断言函数，校验捕获到的业务异常对象中的错误码（CodeValue）是否为预期的 6101
    assertSameValue(6101, $e->getCodeValue());
};

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    $passed++;
    echo '[PASS] ' . $name . PHP_EOL;
}

echo 'Tests run: ' . $passed . ', Failures: 0' . PHP_EOL;
