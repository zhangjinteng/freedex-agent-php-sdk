<?php
declare(strict_types=1);

use Freedex\Agent\AgentClient;
use Freedex\Agent\AgentConfig;
use Freedex\Agent\AgentSigner;
use Freedex\Agent\Exception\AgentApiException;
use Freedex\Agent\Exception\AgentSdkException;
use Freedex\Agent\Http\HttpResponse;
use Freedex\Agent\Http\HttpTransportInterface;
use Freedex\Agent\Model\WalletTransferRequest;
use Freedex\Agent\Model\WalletTransferQueryRequest;
use Freedex\Agent\Model\QueryUserAssetsRequest;
use Freedex\Agent\Examples\OfflineTransport;

require_once __DIR__ . '/../examples/OfflineTransport.php';

function exampleClient(HttpTransportInterface $transport): AgentClient
{
    $nonce = 0;
    return new AgentClient(new AgentConfig('https://offline.invalid', 'AGENT001', 'secret', 'BTC', 10.0, $transport,
        function () use (&$nonce): string { return 'nonce-' . ++$nonce; }, function (): int { return 1800000000; }));
}

$tests['wallet methods preserve complete responses string amounts booleans and original order number'] = function (): void {
    $response = ['code' => 0, 'message' => 'success', 'orderNo' => 'merchant-original', 'status' => 'PROCESSING', 'direction' => 'CONTRACT_TO_FUNDING', 'currency' => 'USDT', 'amount' => '', 'transferAll' => true, 'agentUserId' => 'player', 'platformUserId' => '1188041528', 'resultCode' => 'UPSTREAM_RESULT_UNKNOWN'];
    $transport = new CapturingTransport(200, (string) json_encode($response));
    $client = exampleClient($transport);
    $result = $client->walletTransfer(WalletTransferRequest::byPlatformUserId('merchant-original', '1188041528', 'CONTRACT_TO_FUNDING', null, true));
    foreach ($response as $key => $value) { assertSameValue($value, $result->{$key}); }
    $body = json_decode($transport->lastBody, true);
    assertSameValue('https://offline.invalid/v1/agent/wallet-transfer', $transport->lastUrl);
    assertSameValue(true, $body['transferAll']);
    assertSameValue(false, isset($body['amount']));
    assertSameValue('USDT', $body['currency']);
    assertSameValue(AgentSigner::signParams($body, 'secret'), $body['sign']);
    $client->walletTransfer(WalletTransferRequest::of('fixed-original', 'player', 'PARTNER_TO_FUNDING', '92233720368.54775807'));
    $body = json_decode($transport->lastBody, true);
    assertSameValue('92233720368.54775807', $body['amount']);
    assertSameValue(false, $body['transferAll']);
    assertSameValue(AgentSigner::signParams($body, 'secret'), $body['sign']);
    $client->queryWalletTransfer(WalletTransferQueryRequest::of('merchant-original'));
    assertSameValue('https://offline.invalid/v1/agent/wallet-transfer-status', $transport->lastUrl);
    $body = json_decode($transport->lastBody, true);
    assertSameValue('merchant-original', $body['agentOrderNo']);
    assertSameValue(false, isset($body['currency']));
    assertSameValue(false, isset($body['direction']));
    assertSameValue(5, count($body));
};

$tests['wallet creation never retries HTTP or transport failures'] = function (): void {
    $transport = new CapturingTransport(503, '{"code":6904,"message":"unavailable"}');
    assertThrows(function () use ($transport): void { exampleClient($transport)->walletTransfer(WalletTransferRequest::of('original', 'player', 'PARTNER_TO_FUNDING', '1')); }, AgentApiException::class);
    assertSameValue(1, $transport->calls);
    $transport = new class implements HttpTransportInterface {
        public $calls = 0;
        public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): HttpResponse
        {
            ++$this->calls;
            throw new AgentSdkException('injected ACK loss');
        }
    };
    assertThrows(function () use ($transport): void { exampleClient($transport)->walletTransfer(WalletTransferRequest::of('original', 'player', 'PARTNER_TO_FUNDING', '1')); }, AgentSdkException::class);
    assertSameValue(1, $transport->calls);
};

$tests['includeFunding is optional signed and preserves complete funding balances'] = function (): void {
    $json = '{"code":0,"message":"success","platformUserId":"1188041528","version":9223372036854775808,"funding":{"assetCode":"USDT","availableAmount":"1.00000001","heldAmount":"0","totalAmount":"1.00000001","materialized":false}}';
    $transport = new CapturingTransport(200, $json);
    $client = exampleClient($transport);
    $request = QueryUserAssetsRequest::of('1188041528');
    $client->queryUserAssets($request);
    assertSameValue(false, isset(json_decode($transport->lastBody, true)['includeFunding']));
    $result = $client->queryUserAssets($request->withFunding());
    $body = json_decode($transport->lastBody, true);
    assertSameValue(true, $body['includeFunding']);
    assertSameValue(AgentSigner::signParams($body, 'secret'), $body['sign']);
    assertSameValue('9223372036854775808', $result->version);
    assertSameValue(['assetCode' => 'USDT', 'availableAmount' => '1.00000001', 'heldAmount' => '0', 'totalAmount' => '1.00000001', 'materialized' => false], $result->funding);
    $client->queryUserAssets($request->withFunding(false));
    assertSameValue(false, json_decode($transport->lastBody, true)['includeFunding']);
};

$tests['merchant offline demo queries assets and original order without creating a transfer'] = function (): void {
    $argv = ['merchant.php', '--offline'];
    ob_start();
    require __DIR__ . '/../examples/merchant.php';
    $output = ob_get_clean();
    assertStringContainsValue('Funding fields available: yes', $output);
    assertStringContainsValue('Wallet transfer dry-run:', $output);
    assertStringContainsValue('(not sent)', $output);
    assertStringContainsValue('Wallet query status: PROCESSING, resultCode=UPSTREAM_RESULT_UNKNOWN', $output);
    assertSameValue(['/v1/agent/query-user-assets', '/v1/agent/wallet-transfer-status'], array_column($transport->requests, 'path'));
    assertSameValue(true, $transport->requests[0]['body']['includeFunding']);
    assertSameValue('offline-wallet-order', $transport->requests[1]['body']['agentOrderNo']);
};
