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
    public $platformUserId;
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

    public static function byPlatformUserId(string $agentOrderNo, string $platformUserId, ?string $currency): self
    {
        $req = new self();
        $req->agentOrderNo = $agentOrderNo;
        $req->platformUserId = $platformUserId;
        $req->currency = $currency;
        return $req;
    }

    public function withPlatformUserId(string $platformUserId): self
    {
        $this->platformUserId = $platformUserId;
        return $this;
    }
}
