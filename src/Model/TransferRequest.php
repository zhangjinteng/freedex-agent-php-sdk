<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class TransferRequest extends ArraySerializable
{
    /** @var string */
    public $agentOrderNo;
    /** @var string */
    public $agentUserId;
    /** @var string|null */
    public $platformUserId;
    /** @var string */
    public $direction;
    /** @var string|null */
    public $currency;
    /** @var string */
    public $amount;

    public static function fixed(string $agentOrderNo, string $agentUserId, string $direction, ?string $currency, string $amount): self
    {
        $req = new self();
        $req->agentOrderNo = $agentOrderNo;
        $req->agentUserId = $agentUserId;
        $req->direction = $direction;
        $req->currency = $currency;
        $req->amount = $amount;
        return $req;
    }

    public static function fixedByPlatformUserId(string $agentOrderNo, string $platformUserId, string $direction, ?string $currency, string $amount): self
    {
        $req = new self();
        $req->agentOrderNo = $agentOrderNo;
        $req->platformUserId = $platformUserId;
        $req->direction = $direction;
        $req->currency = $currency;
        $req->amount = $amount;
        return $req;
    }

    public function withPlatformUserId(string $platformUserId): self
    {
        $this->platformUserId = $platformUserId;
        return $this;
    }
}
