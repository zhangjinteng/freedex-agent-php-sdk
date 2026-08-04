<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class QueryAccountRequest extends ArraySerializable
{
    /** @var string|null */
    public $currency;

    public static function of(?string $currency): self
    {
        $req = new self();
        $req->currency = $currency;
        return $req;
    }
}
