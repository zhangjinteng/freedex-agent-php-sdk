<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class QueryOrderRequest extends ArraySerializable
{
    /** @var string */
    public $orderNo;
    /** @var string */
    public $orderType;

    public static function of(string $orderNo, string $orderType): self
    {
        $req = new self();
        $req->orderNo = $orderNo;
        $req->orderType = $orderType;
        return $req;
    }
}
