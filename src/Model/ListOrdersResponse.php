<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class ListOrdersResponse extends AgentResponse
{
    /** @var int */
    public $total = 0;
    /** @var int */
    public $page = 0;
    /** @var int */
    public $pageSize = 0;
    /** @var array<int,array<string,mixed>> */
    public $orders = [];
}
