<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class CreateEntryUrlRequest extends ArraySerializable
{
    /** @var string */
    public $agentUserId;
    /** @var string|null */
    public $redirectPath;
    /** @var string|null */
    public $returnUrl;

    public static function of(string $agentUserId): self
    {
        $req = new self();
        $req->agentUserId = $agentUserId;
        return $req;
    }

    public function withRedirectPath(string $redirectPath): self
    {
        $this->redirectPath = $redirectPath;
        return $this;
    }

    public function withReturnUrl(string $returnUrl): self
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }
}
