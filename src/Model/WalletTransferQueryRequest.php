<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class WalletTransferQueryRequest extends WhitelistedRequest
{
    protected const FIELDS = [
        'agentOrderNo' => 'string',
    ];
    /** @var string|null */
    public $agentOrderNo;

    public static function of(string $orderNo): self
    {
        $request = new self();
        $request->agentOrderNo = $orderNo;
        return $request;
    }
}
