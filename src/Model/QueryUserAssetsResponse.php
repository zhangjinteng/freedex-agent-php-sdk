<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class QueryUserAssetsResponse extends AgentResponse
{
    /** @var string */
    public $platformUserId = '';
    /** @var string */
    public $walletBalance = '';
    /** @var string */
    public $frozenMargin = '';
    /** @var string */
    public $usedMargin = '';
    /** @var string */
    public $availableBalance = '';
    /** @var string */
    public $isolatedMargin = '';
    /** @var int|string */
    public $version = 0;
    /** @var array<string,mixed>|null Funding 的五个公开字段原样保留，缺省不是零余额。 */
    public $funding;
    /** @var bool */
    public $isSimulatedUser = false;
}
