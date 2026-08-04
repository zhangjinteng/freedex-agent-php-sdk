<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class ReverseOrderRequest extends ArraySerializable
{
    /** @var string */
    public $origOrderNo;
    /** @var string */
    public $reverseOrderNo;
    /** @var string */
    public $reverseReason;

    public static function of(string $origOrderNo, string $reverseOrderNo, string $reverseReason): self
    {
        $req = new self();
        $req->origOrderNo = $origOrderNo;
        $req->reverseOrderNo = $reverseOrderNo;
        $req->reverseReason = $reverseReason;
        return $req;
    }
}
