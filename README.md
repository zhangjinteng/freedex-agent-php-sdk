# Freedex Agent PHP SDK

PHP SDK for Freedex Agent REST API.

第一版目标是让代理商接入时不需要自己处理签名、nonce、timestamp、金额字符串和 webhook 验签。SDK 最低兼容 PHP 7.4，但 PHP 7.4 已经停止官方安全支持，生产环境建议使用 PHP 8.1+ 或当前受支持版本。

## 要求

- PHP 7.4+
- `ext-json`
- `ext-curl`
- Composer（用于正式项目 autoload）

## Composer

当前仓库内版本：

```json
{
  "require": {
    "freedex/agent-sdk": "*"
  }
}
```

GitHub 私有仓库可在业务项目里使用 VCS repository：

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/zhangjinteng/freedex-agent-php-sdk.git"
    }
  ],
  "require": {
    "freedex/agent-sdk": "^0.1"
  }
}
```

仓库为私有仓库，安装环境需要具备该仓库的 GitHub 读取权限。Composer 的 VCS repository 会调用 `git clone`，所以业务项目的构建环境需要安装 `git`；CI 中建议通过 `COMPOSER_AUTH` 注入 GitHub Token，不要把 Token 写入仓库。

本地开发可在业务项目里使用 path repository：

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../path/to/exchange/sdks/php/agent-sdk"
    }
  ],
  "require": {
    "freedex/agent-sdk": "*"
  }
}
```

## 初始化

```php
<?php

use Freedex\Agent\AgentClient;
use Freedex\Agent\AgentConfig;

$client = new AgentClient(new AgentConfig(
    'https://agent.example.com',
    'AGENT001',
    'your-api-secret',
    'USDT'
));
```

SDK 会自动注入以下字段：

- `agentCode`
- `timestamp`
- `nonce`
- `sign`

签名算法与 agent 服务端一致：排除 `sign`，空值不参与，按 key ASCII 排序，拼接为 `k=v&k2=v2` 后计算 HMAC-SHA256 hex。

## 绑定用户

```php
use Freedex\Agent\Model\BindRequest;

$resp = $client->bind(BindRequest::of('agent-user-001'));
echo $resp->platformUserId;
```

## 固定金额划转

```php
use Freedex\Agent\Model\Direction;
use Freedex\Agent\Model\TransferRequest;

$resp = $client->transfer(TransferRequest::fixed(
    'AGT-ORDER-1001',
    'agent-user-001',
    Direction::IN,
    'USDT',
    '10.00'
));

echo $resp->orderStatus;
```

`Direction::IN` 表示转入平台，`Direction::OUT` 表示转出平台。金额使用字符串，避免浮点精度问题。

## 全部划出

```php
use Freedex\Agent\Model\TransferAllOutRequest;

$resp = $client->transferAllOut(
    TransferAllOutRequest::of('AGT-ORDER-1002', 'agent-user-001', 'USDT')
);

echo $resp->amount;
```

## 查单

```php
use Freedex\Agent\Model\OrderQueryType;
use Freedex\Agent\Model\QueryOrderRequest;

$resp = $client->queryOrder(
    QueryOrderRequest::of('AGT-ORDER-1001', OrderQueryType::TRANSFER_IN)
);

echo $resp->status;
```

## 创建前端入口链接

```php
use Freedex\Agent\Model\CreateEntryUrlRequest;

$resp = $client->createEntryUrl(
    CreateEntryUrlRequest::of('agent-user-001')->withRedirectPath('/trade')
);

echo $resp->webUrl;
```

## 业务异常

HTTP 非 2xx 或 agent 响应 `code != 0` 时，SDK 抛 `AgentApiException`：

```php
use Freedex\Agent\Exception\AgentApiException;
use Freedex\Agent\Model\OrderQueryType;
use Freedex\Agent\Model\QueryOrderRequest;

try {
    $client->queryOrder(QueryOrderRequest::of('missing-order', OrderQueryType::TRANSFER_IN));
} catch (AgentApiException $e) {
    echo $e->getHttpStatus();
    echo $e->getCodeValue();
    echo $e->getResponseBody();
}
```

网络、序列化、配置错误抛 `AgentSdkException`。

## Webhook 验签

agent 服务推送 webhook 时使用请求头：

- `X-Agent-Timestamp`
- `X-Agent-Nonce`
- `X-Agent-Signature`

验签示例：

```php
use Freedex\Agent\WebhookVerifier;

$ok = WebhookVerifier::verify(
    'your-api-secret',
    $timestampHeader,
    $nonceHeader,
    $rawRequestBody,
    $signatureHeader
);

$idempotencyKey = WebhookVerifier::idempotencyKey($rawRequestBody);
```

`idempotencyKey` 格式为 `orderType:orderId:targetStatus`，可用于代理商侧 webhook 幂等处理。

## 测试

本仓库提供一个不依赖 PHPUnit 的基础测试 runner：

```bash
php tests/run.php
```

当前开发环境如果没有本机 PHP，可以用已有 PHP Docker 镜像运行：

```bash
docker run --rm -v "$PWD/../../../":/work -w /work/sdks/php/agent-sdk ccr.ccs.tencentyun.com/cddzg/platform_admin_php:latest php tests/run.php
```
