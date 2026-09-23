<?php
declare(strict_types=1);

namespace Freedex\Agent\Examples;

use Freedex\Agent\Http\HttpResponse;
use Freedex\Agent\Http\HttpTransportInterface;

// 固定本地响应，不打开套接字；商户示例仅调用资产和资金订单查询。
class OfflineTransport implements HttpTransportInterface
{
    public $requests = [];

    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): HttpResponse
    {
        $request = json_decode($body ?? '{}', true);
        $path = parse_url($url, PHP_URL_PATH);
        $this->requests[] = ['path' => $path, 'body' => $request];
        if ($path === '/v1/agent/wallet-transfer' || $path === '/v1/agent/wallet-transfer-status') {
            return self::response(['code' => 0, 'message' => 'offline mock', 'orderNo' => $request['agentOrderNo'], 'status' => 'PROCESSING', 'direction' => 'FUNDING_TO_CONTRACT', 'currency' => 'USDT', 'amount' => '', 'transferAll' => true, 'agentUserId' => 'demo-player', 'platformUserId' => '1188041528', 'resultCode' => 'UPSTREAM_RESULT_UNKNOWN']);
        } elseif ($path === '/v1/agent/query-user-assets') {
            return self::response(['code' => 0, 'message' => 'offline mock', 'platformUserId' => '1188041528', 'walletBalance' => '10.00000001', 'funding' => ['assetCode' => 'USDT', 'availableAmount' => '2.00000001', 'heldAmount' => '0', 'totalAmount' => '2.00000001', 'materialized' => true], 'isSimulatedUser' => false]);
        }
        throw new \RuntimeException('unsupported offline demo route');
    }

    private static function response(array $body): HttpResponse
    {
        return new HttpResponse(200, (string) json_encode($body));
    }
}
