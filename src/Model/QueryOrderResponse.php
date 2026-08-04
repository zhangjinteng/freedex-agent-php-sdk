<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class QueryOrderResponse extends AgentResponse
{
    /** @var string */
    public $orderType = '';
    /** @var string */
    public $orderNo = '';
    /** @var string */
    public $status = '';
    /** @var string */
    public $direction = '';
    /** @var string */
    public $transferMode = '';
    /** @var string */
    public $currency = '';
    /** @var string */
    public $amount = '';
    /** @var string */
    public $agentUserId = '';
    /** @var string */
    public $platformUserId = '';
    /** @var string */
    public $failReason = '';
    /** @var string */
    public $createdAt = '';
    /** @var string */
    public $completedAt = '';
}
