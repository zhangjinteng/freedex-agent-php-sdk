<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class BindResponse extends AgentResponse
{
    /** @var string */
    public $platformUserId = '';
    /** @var string */
    public $bindStatus = '';
}
