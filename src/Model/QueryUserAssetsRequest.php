<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class QueryUserAssetsRequest extends WhitelistedRequest
{
    protected const FIELDS = ['platformUserId' => 'string', 'includeFunding' => 'boolean'];

    /** @var bool|null */
    public $includeFunding;

    public function withFunding(bool $include = true): self
    {
        $this->includeFunding = $include;
        return $this;
    }

    /** @var string */
    public $platformUserId;

    public static function of(string $platformUserId): self
    {
        $req = new self();
        $req->platformUserId = $platformUserId;
        return $req;
    }
}
