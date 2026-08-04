<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class TransferAllOutRequest extends ArraySerializable
{
    /** @var string */
    public $agentOrderNo;
    /** @var string */
    public $agentUserId;
    /** @var string|null */
    public $currency;

    public static function of(string $agentOrderNo, string $agentUserId, ?string $currency): self
    {
        $req = new self();
        $req->agentOrderNo = $agentOrderNo;
        $req->agentUserId = $agentUserId;
        $req->currency = $currency;
        return $req;
    }
}
