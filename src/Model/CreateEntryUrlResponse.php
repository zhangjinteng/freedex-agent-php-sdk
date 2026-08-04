<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class CreateEntryUrlResponse extends AgentResponse
{
    /** @var string */
    public $webUrl = '';
    /** @var int */
    public $expireAt = 0;
}
