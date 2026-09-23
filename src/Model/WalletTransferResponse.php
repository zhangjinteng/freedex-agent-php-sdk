<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class WalletTransferResponse extends AgentResponse
{
    /** @var string */
    public $orderNo = '';
    /** @var string */
    public $status = '';
    /** @var string */
    public $direction = '';
    /** @var string */
    public $currency = '';
    /** @var string */
    public $amount = '';
    /** @var bool */
    public $transferAll = false;
    /** @var string */
    public $agentUserId = '';
    /** @var string */
    public $platformUserId = '';
    /** @var string */
    public $resultCode = '';
}
