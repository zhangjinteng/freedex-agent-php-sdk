<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/OfflineTransport.php';

use Freedex\Agent\AgentClient;
use Freedex\Agent\AgentConfig;
use Freedex\Agent\Examples\OfflineTransport;
use Freedex\Agent\Model\WalletTransferRequest;
use Freedex\Agent\Model\WalletTransferQueryRequest;
use Freedex\Agent\Model\QueryUserAssetsRequest;

try {
    $mode = $argv[1] ?? '--offline';
    if (!in_array($mode, ['--offline', '--live-readonly'], true) || count($argv) > 2) {
        throw new RuntimeException('Usage: php examples/merchant.php [--offline|--live-readonly]');
    }
    $offline = $mode === '--offline';
    $base = $offline ? 'https://offline.invalid' : (string) getenv('AGENT_BASE_URL');
    $parts = parse_url($base);
    if (!$offline && (($parts['scheme'] ?? '') !== 'https') && !(($parts['scheme'] ?? '') === 'http' && in_array($parts['host'] ?? '', ['127.0.0.1', '[::1]'], true))) {
        throw new RuntimeException('live-readonly requires HTTPS or a literal loopback HTTP address');
    }
    $transport = $offline ? new OfflineTransport() : null;
    $client = new AgentClient(new AgentConfig($base, $offline ? 'offline-agent' : (string) getenv('AGENT_CODE'), $offline ? 'offline-secret' : (string) getenv('AGENT_API_SECRET'), 'USDT', 10.0, $transport));
    $publicId = $offline ? '1188041528' : (string) getenv('AGENT_PUBLIC_USER_ID');
    if ($publicId !== '') {
        $assets = $client->queryUserAssets(QueryUserAssetsRequest::of($publicId)->withFunding());
        echo 'Funding fields available: ' . ($assets->funding !== null ? 'yes' : 'no') . PHP_EOL;
    }
    if ($offline) {
        // 仅构造并校验请求，不调用资金写接口；真实划转须由商户明确调用 SDK。
        $preview = WalletTransferRequest::of('offline-wallet-order', 'demo-player', 'FUNDING_TO_CONTRACT', null, true)->toArray();
        echo 'Wallet transfer dry-run: ' . json_encode($preview, JSON_UNESCAPED_SLASHES) . ' (not sent)' . PHP_EOL;
    }
    $orderNo = $offline ? 'offline-wallet-order' : (string) getenv('AGENT_WALLET_ORDER_NO');
    if ($orderNo !== '') {
        $order = $client->queryWalletTransfer(WalletTransferQueryRequest::of($orderNo));
        echo 'Wallet query status: ' . $order->status . ', resultCode=' . $order->resultCode . PHP_EOL;
    }
} catch (Throwable $error) {
    // 不自动重试请求，不把未知资金结果当成功或失败到账。
    fwrite(STDERR, 'Demo stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
