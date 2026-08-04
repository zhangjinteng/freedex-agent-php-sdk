<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class BindRequest extends ArraySerializable
{
    /** @var string */
    public $agentUserId;
    /** @var string|null */
    public $ext;

    public static function of(string $agentUserId): self
    {
        $req = new self();
        $req->agentUserId = $agentUserId;
        return $req;
    }

    public function withExt(string $ext): self
    {
        $this->ext = $ext;
        return $this;
    }
}
