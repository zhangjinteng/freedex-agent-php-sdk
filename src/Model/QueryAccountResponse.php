<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class QueryAccountResponse extends AgentResponse
{
    /** @var string */
    public $agentCode = '';
    /** @var string */
    public $agentStatus = '';
    /** @var array<int,array<string,mixed>> */
    public $assets = [];
}
