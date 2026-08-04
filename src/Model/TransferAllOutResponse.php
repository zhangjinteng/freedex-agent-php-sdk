<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class TransferAllOutResponse extends AgentResponse
{
    /** @var string */
    public $orderNo = '';
    /** @var string */
    public $orderStatus = '';
    /** @var string */
    public $amount = '';
}
