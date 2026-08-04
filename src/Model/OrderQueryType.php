<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

final class OrderQueryType
{
    public const TRANSFER_IN = 'TRANSFER_IN';
    public const TRANSFER_OUT = 'TRANSFER_OUT';

    private function __construct()
    {
    }
}
