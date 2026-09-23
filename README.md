# 6MM Agent PHP SDK

合作商后台调用 6MM Agent REST API 的 PHP SDK，提供请求签名、用户绑定、上下分、Funding 钱包划转、余额查询、入口链接和 Webhook 验签。

**后台研发请先阅读 [PHP SDK 后台对接文档](docs/backend-integration.md)。** 文档包含安装、完整方法表、可复制示例、金额约束、订单幂等、异常处理与联调步骤。

## 安装

要求 PHP 7.4 或以上、`ext-json`、`ext-curl`，使用 Composer 自动加载。部署时请选择组织仍维护的 PHP 运行环境。

在业务项目的 `composer.json` 合并以下配置：

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

在业务项目运行 `composer update freedex/agent-sdk`，提交业务项目的 `composer.lock`；部署使用 `composer install` 复用锁定提交。需要仓库权限时，通过构建环境的 Git 凭据配置访问，不把凭据写入 URL 或提交到代码库。Funding 钱包能力从 `v0.2.0` 起提供。

## 初始化与余额查询

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Freedex\Agent\AgentClient;
use Freedex\Agent\AgentConfig;
use Freedex\Agent\Model\QueryUserAssetsRequest;

$client = new AgentClient(new AgentConfig(
    (string) getenv('AGENT_BASE_URL'),
    (string) getenv('AGENT_CODE'),
    (string) getenv('AGENT_API_SECRET')
));

$assets = $client->queryUserAssets(
    QueryUserAssetsRequest::of('1188041528')->withFunding()
);
```

`AGENT_BASE_URL` 是平台提供的 **Agent API 服务地址**，不含 `/v1/agent` 路径；SDK 会追加具体路由。示例用户 ID 必须替换为绑定接口返回的真实公开 ID。

## 钱包划转

```php
use Freedex\Agent\Model\WalletTransferRequest;
use Freedex\Agent\Model\WalletTransferQueryRequest;

// 后台先保存业务订单和原单号，再在受控业务入口提交一次。
$request = WalletTransferRequest::of(
    'merchant-wallet-001', 'partner-user-001', 'FUNDING_TO_CONTRACT', '10'
);
$result = $client->walletTransfer($request);

// 处理中或创建结果未知时，后续使用原单号查单。
$latest = $client->queryWalletTransfer(
    WalletTransferQueryRequest::of('merchant-wallet-001')
);
```

支持 `PARTNER_TO_FUNDING`、`FUNDING_TO_PARTNER`、`FUNDING_TO_CONTRACT`、`CONTRACT_TO_FUNDING`。仅支持 USDT；金额使用规范十进制字符串，例如 `10`、`0.01`，不传 `10.00`、浮点数或科学计数法。Funding ↔ Contract 支持 `amount=null, transferAll=true`。

只有 `status=SUCCESS` 表示钱包订单已确认成功；`code=0`、HTTP 200 或非空订单号都不能单独证明到账。SDK 不自动重试创建；未知结果保留原单查单，不能换单号重新划款。

## 支持范围

- 原有用户绑定、固定金额上下分、全部划出、冲正、查单、代理商资金和用户资产查询。
- 新增 Funding 钱包划转、原单号查询、`includeFunding` 余额查询。
- 前端直接跳转入口、服务版本、Webhook 验签。
- **未封装** `createEmbedToken`、`queryExchangeRates`、`listSupportedFiatCurrencies`；不包含合约订单、成交、仓位查询扩展。

## 离线验证

```bash
php tests/run.php
php examples/merchant.php
```

测试和默认 Demo 使用模拟传输，无需服务凭据且不会发起网络或资金请求。Demo 的 `--live-readonly` 模式只查询真实资产和已有资金单，参数见 [后台对接文档](docs/backend-integration.md)。

源码来源和验证记录见 [交付记录](docs/delivery.md)。GitHub 版在上游 SDK 基础上保留了旧版 `AgentClient` 的调试信息访问方法，以兼容现有合作商后台。
