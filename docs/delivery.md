# PHP SDK 独立仓库交付记录

## 来源与范围

- 源仓库：`exchange_workspace`。
- 源分支：`evolution`。
- 固定源码提交：`83944d389931edd7c752a90fd59e3afa4560ef81`。
- 源目录：`sdks/php/agent-sdk`。
- 源 SDK 最近变更：`5c84c55e4`，保留钱包接口并撤回合约查询扩展。
- 目标仓库：[6MM/agent-php-sdk](https://igitea.com/6MM/agent-php-sdk)。目标初始为空，交付至 `main` 分支，不创建版本 tag。

上述记录描述最初的 Gitea 交付。随后将 SDK 源码及文档同步至 [GitHub 仓库](https://github.com/zhangjinteng/freedex-agent-php-sdk) 的 `main` 分支，并保留 GitHub 旧版 `AgentClient` 的调试信息访问方法，以兼容现有合作商后台。GitHub 原有的 `v0.1.0` tag 仍指向旧版，不包含 Funding 钱包能力；Funding 钱包能力从 `v0.2.0` 起提供。

本次原样同步 SDK 的 `src/`、`tests/`、`examples/` 和 `composer.json`。只重写独立仓库 README、增加中文后台对接文档及 Git 忽略规则。没有修改 Exchange 服务端、合并生产主线或执行部署。

本版支持原有上下分、Funding 四方向操作、全额 Funding ↔ Contract、Funding 余额查询。未增加内嵌 token、汇率或合约查询方法。

## 验证

本次独立仓库交付已完成以下本地验证：

| 检查 | 结果 |
| --- | --- |
| PHP 7.4.33 | 14 项测试通过，全部 PHP 文件语法通过，离线 Demo 通过 |
| PHP 8.4.25 | 14 项测试通过，全部 PHP 文件语法通过，离线 Demo 通过 |
| Composer 2.10.3 | `validate --strict --no-interaction` 通过 |
| 文档示例 | 13 段 PHP 示例语法通过，JSON 示例可解析，相对链接目标存在 |
| 源码一致性 | 42 个源码、测试、示例和 manifest 文件与固定上游提交逐字节一致 |

PHP 容器测试均禁用网络并只读挂载 SDK，使用模拟传输；没有发送真实资金请求。Composer 校验在无版本 tag 的初始化仓库提示推断 root version，此提示不代表本 SDK 发布了 `1.0.0`；安装约束使用已交付的 `dev-main`。

真实环境的接口开通、实际资金到账及合作商本地账对账不属于本次离线验证结论。

## 交付与恢复

代码及文档保存在目标仓库 `main`，可通过 Git 提交历史恢复。本地独立仓库保留作为文档与源码交付位置；任务开发分支在远端提交核验完成后删除。未创建 Exchange 临时 worktree，未修改 Exchange 的 `master` 或 `evolution`。
