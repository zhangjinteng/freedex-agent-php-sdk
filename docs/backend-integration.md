# 6MM Agent PHP SDK 后台对接文档

适用对象：合作商、代理商后台 PHP 研发及联调同事。

本文对应本仓库 `main` 分支中的 PHP SDK，来源为 `evolution` 已集成的钱包能力；具体来源见 [交付记录](delivery.md)。SDK 包含新钱包接口，不代表任意环境都已开启对应服务能力。接入地址、Agent 凭据、IP 白名单、用户绑定及 Funding 开通情况由平台提供。

## 1. 接入对象与资金边界

合作商后台调用 **Agent API**；本 SDK 不调用浏览器网关的 `/asset/v1/private/account/transfer`。

| 钱包 | 含义 | 由谁记账 |
| --- | --- | --- |
| 合作商钱包（PARTNER） | 合作商自己系统内的用户余额 | 合作商后台 |
| 资金账户（FUNDING） | 平台资金余额和冻结金额 | 平台资金服务 |
| 合约账户（CONTRACT） | 平台合约交易余额、保证金等 | 平台交易核心 |

Agent API 不读取或控制合作商本地用户钱包。合作商必须自行保存业务订单，并在本地数据库事务内完成预留、扣款、入账和幂等控制。平台代理商保证金也不是合作商本地用户钱包。

Funding ↔ Contract 只移动该用户的两个平台账户余额，不应扣减或增加合作商本地钱包。

## 2. 安装与初始化

要求 PHP 7.4+、JSON 和 cURL 扩展、Composer，以及能访问 SDK 仓库的 Git 环境。运行环境版本由后台部署规范确定。

在现有业务项目的 `composer.json` 合并：

```json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/zhangjinteng/freedex-agent-php-sdk.git"}
  ],
  "require": {
    "freedex/agent-sdk": "^0.2.0"
  }
}
```

运行 `composer update freedex/agent-sdk` 安装或更新，提交业务项目 `composer.lock`。后续部署执行 `composer install`，不要在每次部署时自动追最新分支。Funding 钱包能力从 `v0.2.0` 起提供；`v0.1.0` 不包含这些能力。私有仓库凭据通过构建环境配置，不放入上述 JSON。

通过密钥管理或环境变量配置：

| 配置 | 说明 |
| --- | --- |
| `AGENT_BASE_URL` | 平台提供的 Agent API HTTPS 根地址，不含 `/v1/agent` |
| `AGENT_CODE` | 分配给当前合作商的编码 |
| `AGENT_API_SECRET` | 仅保存在后台的签名密钥 |

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Freedex\Agent\AgentClient;
use Freedex\Agent\AgentConfig;

$client = new AgentClient(new AgentConfig(
    (string) getenv('AGENT_BASE_URL'),
    (string) getenv('AGENT_CODE'),
    (string) getenv('AGENT_API_SECRET'),
    'USDT',
    10.0
));
```

最后一个参数是 HTTP 超时秒数，默认 10 秒。超时仅表示本次调用未拿到确定结果，不能据此认定没有扣款。以下示例承接此 `$client`，用户 ID 和订单号必须替换成业务系统的数据。资金创建示例会产生真实业务请求，联调时使用约定的测试环境与账号。

## 3. 本版 SDK 方法表

除 `version()` 为 GET 外，表中方法均为带签名的 POST。

| SDK 方法 | HTTP 路径 | 请求模型 / 用途 |
| --- | --- | --- |
| `bind` | `/v1/agent/bind` | `BindRequest`，注册并绑定用户 |
| `transfer` | `/v1/agent/transfer` | `TransferRequest`，合作商 ↔ 合约固定金额上下分 |
| `transferAllOut` | `/v1/agent/transfer-all-out` | `TransferAllOutRequest`，合约向合作商全部划出 |
| `walletTransfer` | `/v1/agent/wallet-transfer` | `WalletTransferRequest`，四方向钱包资金操作 |
| `queryWalletTransfer` | `/v1/agent/wallet-transfer-status` | `WalletTransferQueryRequest`，新钱包订单查询 |
| `queryOrder` | `/v1/agent/query-order` | `QueryOrderRequest`，原有上下分订单查询 |
| `listOrders` | `/v1/agent/list-orders` | `ListOrdersRequest`，原有订单列表 |
| `reverse` | `/v1/agent/reverse` | `ReverseOrderRequest`，原有订单冲正 |
| `queryAccount` | `/v1/agent/query-account` | `QueryAccountRequest`，代理商资金 |
| `queryUserAssets` | `/v1/agent/query-user-assets` | `QueryUserAssetsRequest`，用户合约资产及可选 Funding |
| `createEntryUrl` | `/v1/agent/create-entry-url` | `CreateEntryUrlRequest`，前端直接跳转链接 |
| `version` | `/version` | 无请求模型，服务版本 |

本版 **没有封装** `createEmbedToken`、`queryExchangeRates`、`listSupportedFiatCurrencies`，也没有合约订单、成交、仓位查询方法。不要按 Java SDK 的方法名直接调用 PHP SDK。

## 4. 用户绑定和 ID 保存

```php
use Freedex\Agent\Model\BindRequest;

$bound = $client->bind(
    BindRequest::of('partner-user-001')->withUsername('示例用户')
);
$platformUserId = $bound->platformUserId;
```

合作商在本地持久化 `agentUserId ↔ platformUserId` 的映射。

- `agentUserId` 是合作商自身的用户标识。
- `platformUserId` 是平台返回的公开用户 ID，始终按字符串存储和传递；不要传数据库内部 `user_id`。
- 新钱包创建允许两者至少提供一个；同时提供时必须指向同一绑定。
- 用户必须已绑定当前 Agent，Agent 与用户必须属于同一平台站点。
- 用户类型由平台确定，不通过 SDK 自行指定真实或模拟身份。

## 5. 余额查询

```php
use Freedex\Agent\Model\QueryUserAssetsRequest;

$assets = $client->queryUserAssets(
    QueryUserAssetsRequest::of($platformUserId)->withFunding()
);
$contractAvailable = $assets->availableBalance;
$funding = $assets->funding;
if ($funding !== null) {
    $fundingAvailable = $funding['availableAmount'];
    $fundingHeld = $funding['heldAmount'];
    $fundingTotal = $funding['totalAmount'];
}
```

`walletBalance`、`availableBalance` 等旧字段仍表示合约账户。Funding 在独立的 `funding` 数组内，包含 `assetCode / availableAmount / heldAmount / totalAmount / materialized`。

不调用 `withFunding()` 时保持旧查询行为，不请求 Funding。`withFunding(false)` 会显式发送并签名 `false`。`funding=null` 表示没有返回该部分，不应转换为零余额；合法的尚未创建账户余额可返回零金额及 `materialized=false`。

金额保留十进制字符串；版本号可能为整数或超出 PHP 整数范围的字符串，不能转为浮点数。两个平台账户的余额是先后查询的快照，不是跨账户原子快照。

## 6. 新钱包划转

### 6.1 方向

| `direction` | 资金方向 | 固定金额 | `transferAll=true` |
| --- | --- | --- | --- |
| `PARTNER_TO_FUNDING` | 合作商 → 平台资金账户 | 支持 | 不支持 |
| `FUNDING_TO_PARTNER` | 平台资金账户 → 合作商 | 支持 | 不支持 |
| `FUNDING_TO_CONTRACT` | 平台资金账户 → 平台合约账户 | 支持 | 支持 |
| `CONTRACT_TO_FUNDING` | 平台合约账户 → 平台资金账户 | 支持 | 支持 |

合作商 ↔ 合约账户沿用第 8 节的旧接口，不能把 `PARTNER_TO_CONTRACT` 传给 `walletTransfer`。

### 6.2 请求约束

| 字段 | 要求 |
| --- | --- |
| `agentOrderNo` | 必填，最长 128；禁止首尾空白、换行和 NUL；建议使用 ASCII 唯一业务编号 |
| `agentUserId` / `platformUserId` | 至少一个，必须对应当前 Agent 的绑定用户 |
| `direction` | 上表四种字符串之一 |
| `currency` | 仅 `USDT`，模型默认此值 |
| `amount` | 固定金额必填；规范十进制字符串，最多 8 位小数，范围 `(0, 92233720368.54775807]` |
| `transferAll` | PHP 布尔值，默认 `false`；只适用于 Funding ↔ Contract |

合法金额：`'10'`、`'0.01'`、`'123.45678901'`。不合法：`'10.00'`、`'01'`、`'+1'`、`'1e2'`、`'0'`、浮点数。SDK 保留输入字符串，不自动规范化或四舍五入；可在业务金额库中进行精确处理后再传入。

`transferAll=true` 时 `amount` 传 `null` 或空字符串，由服务端执行时确定实际金额；不要先查询余额再用查到的数额模拟全额操作。合约全额划出受上游持仓保护，明确拒绝后可根据业务意图另行执行固定金额操作。

### 6.3 固定金额示例

```php
use Freedex\Agent\Model\WalletTransferRequest;

// 此订单号先在本地订单事务内生成并保存，一次业务操作只使用一个原单号。
$orderNo = 'wallet-20260923-000001';
$request = WalletTransferRequest::of(
    $orderNo,
    'partner-user-001',
    'FUNDING_TO_CONTRACT',
    '10'
);
$result = $client->walletTransfer($request);
```

使用平台公开 ID 时改用：

```php
$request = WalletTransferRequest::byPlatformUserId(
    $orderNo, $platformUserId, 'FUNDING_TO_CONTRACT', '10'
);
```

### 6.4 全额示例

```php
$request = WalletTransferRequest::of(
    'wallet-20260923-000002',
    'partner-user-001',
    'CONTRACT_TO_FUNDING',
    null,
    true
);
$result = $client->walletTransfer($request);
```

### 6.5 响应和查单

创建与查询都返回 `WalletTransferResponse`。示例：

```json
{
  "code": 0,
  "message": "success",
  "orderNo": "wallet-20260923-000001",
  "status": "SUCCESS",
  "direction": "FUNDING_TO_CONTRACT",
  "currency": "USDT",
  "amount": "10",
  "transferAll": false,
  "agentUserId": "partner-user-001",
  "platformUserId": "1188041528",
  "resultCode": "OK"
}
```

| `status` | 后台含义与处理 |
| --- | --- |
| `SUCCESS` | 已确认成功且 Agent 已提交本地终态；合作商按原单号最多结算一次 |
| `FAILED` | 原单已明确拒绝；按原单号最多释放本地预留一次 |
| `PROCESSING` | 正在处理或结果尚未确认；保留原单和预留，继续查单 |
| 其他值 | 按待核对处理，不推断为成功或失败 |

HTTP 200、`code=0`、存在订单号或非空 `amount` 都不能代替终态判断。全额模式在尚未获得上游实际金额时可能返回 `amount=""`，其含义是未知，不能补成零。

```php
use Freedex\Agent\Model\WalletTransferQueryRequest;

$latest = $client->queryWalletTransfer(
    WalletTransferQueryRequest::of($orderNo)
);
```

钱包订单只用 `queryWalletTransfer` 查，不能用旧 `queryOrder` 替代。调用方使用原 `agentOrderNo`，无需保存或提供内部资金命令 ID。

## 7. 本地订单、幂等与异常处理

建议后台保存：本地订单 ID、Agent 标识、`agentOrderNo`、两侧用户标识、方向、币种、请求金额、全额标记、实际金额、平台状态、`resultCode`、本地结算标记、创建及最后查询时间。金额用字符串或精确 DECIMAL；对 `(Agent 标识, agentOrderNo)` 建唯一约束。

1. 在本地事务中保存原单和不可变请求参数；涉及合作商转出时，先完成本地余额预留。
2. 受控发送一次 `walletTransfer`，保存响应；应用进程重启后从本地待处理单恢复，不能重新生成订单号。
3. 收到 `SUCCESS` 或查到成功后，在本地事务内检查结算标记，并且只结算一次。收到明确 `FAILED` 后也只释放预留一次。
4. 超时、网络错误、响应无法解析或 `PROCESSING` 均保留待核对状态，按原单号查单；查询可以采用有上限的退避间隔，超出业务等待时间转人工核对。
5. 业务层需要创建新操作时，必须先确认原操作结果，不能通过换单号“重试”未知结果。

服务端对同一 Agent 的同号同参请求恢复原单，不再次发送资金 RPC；同号但用户、站点、方向、金额或模式不同会拒绝。SDK 自身不自动重试资金创建。

```php
use Freedex\Agent\Exception\AgentApiException;
use Freedex\Agent\Exception\AgentSdkException;

try {
    $latest = $client->queryWalletTransfer(
        WalletTransferQueryRequest::of($orderNo)
    );
    // 交给本地订单服务按状态和幂等标记推进，不在此处直接修改余额。
} catch (AgentApiException $e) {
    $httpStatus = $e->getHttpStatus();
    $businessCode = $e->getCodeValue();
    // 保留原单并按错误分类处理；异常本身不代表原资金请求已失败。
} catch (AgentSdkException $e) {
    // 网络、配置、序列化或解析异常；保留原单，交由后台核对流程处理。
}
```

SDK 对 HTTP 非 2xx 或响应 `code!=0` 抛 `AgentApiException`。HTTP 非 2xx 分支的 `getCodeValue()` 当前可能为 `0`，不能把它当成功；如需排查，可读取 `getResponseBody()`，日志须按业务要求脱敏。网络等问题抛 `AgentSdkException`。

| 错误 / 结果 | 处理建议 |
| --- | --- |
| `102001 / 102002 / 102003` | 缺参 / 参数值或格式非法 / 数值越界；修正请求，先区分是否涉及已有原单 |
| `6001 / 6002 / 6003 / 6006` | 签名 / 时间戳 / nonce 重用 / IP 白名单；检查配置与后台时钟 |
| `6010 / 6101 / 6102` | Agent 站点未映射 / 用户未绑定或归属不符 / 绑定锁定；由平台核对 |
| `6109` | 当前查询未找到订单；不能仅据此断言先前超时请求未执行 |
| `6902 / 6904` | 数据库或服务不可用；创建后遇到此类错误应保留原单核对 |
| `UPSTREAM_RESULT_UNKNOWN` | 上游结果未确认，继续查原单 |
| `TRANSFER_REFERENCE_MISSING / UPSTREAM_RECORD_NOT_FOUND` | 缺少查询依据或上游查无记录，必要时人工核对，不能自动补发 |
| `INVALID_UPSTREAM_RESPONSE` | 响应未通过契约校验，保持待核对 |
| `INSUFFICIENT_AGENT_MARGIN` | 平台代理商保证金不足，钱包接口会保存明确失败原单 |

只以钱包原单 `status` 和核验结果决定资金结算。不要仅根据异常类型或 `resultCode` 文本执行退款。新钱包订单没有本 SDK 的通用冲正接口，也不应套用旧订单 Webhook；使用专门查单流程恢复。

## 8. 原有合作商 ↔ 合约上下分

这组接口不经过用户 Funding 钱包，不能与第 6 节混用。`Direction::IN` 为合作商转入合约，`Direction::OUT` 为合约转出到合作商。

```php
use Freedex\Agent\Model\Direction;
use Freedex\Agent\Model\TransferRequest;
use Freedex\Agent\Model\TransferAllOutRequest;
use Freedex\Agent\Model\OrderQueryType;
use Freedex\Agent\Model\QueryOrderRequest;

$legacy = $client->transfer(TransferRequest::fixed(
    'contract-20260923-000001', 'partner-user-001', Direction::IN, 'USDT', '10'
));
// 原接口的创建状态字段叫 orderStatus，不是新钱包接口的 status。
$legacyStatus = $legacy->orderStatus;

$original = $client->queryOrder(QueryOrderRequest::of(
    'contract-20260923-000001', OrderQueryType::TRANSFER_IN
));
// queryOrder 的返回字段则是 status。
$queriedStatus = $original->status;

// 这是单独的一次全部划出业务操作，需要独立原单号。
$allOut = $client->transferAllOut(
    TransferAllOutRequest::of('contract-20260923-000002', 'partner-user-001', 'USDT')
);
```

`SUCCESS` 才确认成功；处理中保留原单查询。模拟用户规则和代理商保证金处理由平台确定，后台不能自行指定。冲正仅适用于服务端允许冲正的原有订单，应由受控业务入口调用 `reverse(ReverseOrderRequest::of($origOrderNo, $reverseOrderNo, $reason))`，不是超时处理手段。

其他查询模型：`QueryAccountRequest::of('USDT')` 查询代理商资金；`ListOrdersRequest::page(1, 20)` 查询原有订单列表，可按模型属性增加筛选。这两个接口不替代用户 Funding 余额或新钱包查单。

## 9. 前端直接跳转入口

```php
use Freedex\Agent\Model\CreateEntryUrlRequest;

$entry = $client->createEntryUrl(
    CreateEntryUrlRequest::of('partner-user-001')
        ->withRedirectPath('/trade/BTCUSDT')
        ->withReturnUrl('https://partner.example/return')
);
$webUrl = $entry->webUrl;
```

后台将 `webUrl` 返回给已认证的当前用户前端，并按平台有效期使用。`returnUrl` 是退出回跳用的 HTTP/HTTPS 绝对地址。密钥留在后台；不要让浏览器直接携带 Agent 密钥调用接口。内嵌 Widget 的 token 接口尚未封装，不能把直接跳转链接当作内嵌 token。

## 10. 签名与 Webhook

SDK 自动为每次 HTTP 调用加入 `agentCode / timestamp / nonce / sign`。请求签名规则：去掉 `sign` 和空值，按字段名 ASCII 排序，直接拼接 `k=v&k2=v2`，计算 HMAC-SHA256 小写十六进制。布尔值按 `true/false` 字符串参与签名；不得漏掉 `includeFunding` 或 `transferAll`。

服务端时间戳使用 Unix 秒，当前容忍偏移 300 秒；nonce 按 Agent 防重放。业务原单号保持不变，新的查单 HTTP 请求仍由 SDK 生成新的时间戳和 nonce。不要重新使用已经签好的原始 JSON 请求体。

旧订单 Webhook 头为 `X-Agent-Timestamp / X-Agent-Nonce / X-Agent-Signature`：

```php
use Freedex\Agent\WebhookVerifier;

$valid = WebhookVerifier::verify(
    (string) getenv('AGENT_API_SECRET'),
    $timestampHeader,
    $nonceHeader,
    $rawRequestBody,
    $signatureHeader
);
$idempotencyKey = $valid
    ? WebhookVerifier::idempotencyKey($rawRequestBody)
    : '';
```

必须用收到的原始 body 验签，不能先反序列化再序列化。上述变量来自后台框架的请求头和原始请求体。验签函数只检查签名，不代替调用方的时间、重放和业务归属校验。

验签通过并核对订单归属后，以 `orderType:orderId:targetStatus` 保存唯一处理记录，并与本地订单结算一起提交；重复通知不能重复入账。旧订单事件包括 `transfer.completed`、`reverse.completed`、`transfer.dead`，不保证覆盖新钱包订单；新钱包始终保留第 6 节查单流程。

## 11. 联调与上线交接

1. 安装 SDK 并锁定 `composer.lock`；运行 `php tests/run.php` 和 `php examples/merchant.php` 完成离线验证。
2. 核对 Agent API 地址、凭据、后台出口 IP、站点、绑定用户及 Funding 读写开通情况。
3. 先验证用户绑定、合约余额、`withFunding()` 和原单查询；确认真实/模拟用户标识与业务预期一致。
4. 在约定测试环境分别验证四方向小额钱包操作；核对平台原单与合作商本地订单，只结算一次。
5. 验证同号同参、同号异参、超时后查单、重复查询、重启恢复和重复 Webhook；测试过程中不通过换号规避未知原单。
6. 验证全额模式实际金额、持仓拒绝和零可用余额等返回，确认未把处理中空金额当零。
7. 完成双方对账后按后台发布流程启用；查询或写入开关的具体开启由平台侧负责。

默认 Demo 完全离线。需要查询真实环境已有记录时，设置前述环境变量，并按需增加 `AGENT_PUBLIC_USER_ID`、`AGENT_WALLET_ORDER_NO`，执行：

```bash
php examples/merchant.php --live-readonly
```

此模式仅查询，不创建资金单；请求失败即停止。SDK 离线测试通过不等同于真实资金到账验证。本次仓库同步未执行远端资金操作。
