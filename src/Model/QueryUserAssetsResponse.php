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
    /** @var int */
    public $version = 0;
}
