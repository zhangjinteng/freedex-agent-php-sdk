<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class QueryUserAssetsRequest extends ArraySerializable
{
    /** @var string */
    public $platformUserId;

    public static function of(string $platformUserId): self
    {
        $req = new self();
        $req->platformUserId = $platformUserId;
        return $req;
    }
}
