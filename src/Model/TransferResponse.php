<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class TransferResponse extends AgentResponse
{
    /** @var string */
    public $orderNo = '';
    /** @var string */
    public $orderStatus = '';
}
