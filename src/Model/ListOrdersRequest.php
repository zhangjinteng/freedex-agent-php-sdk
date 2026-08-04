<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class ListOrdersRequest extends ArraySerializable
{
    /** @var string|null */
    public $orderType;
    /** @var string|null */
    public $status;
    /** @var string|null */
    public $startTime;
    /** @var string|null */
    public $endTime;
    /** @var int */
    public $page = 1;
    /** @var int */
    public $pageSize = 20;

    public static function page(int $page, int $pageSize): self
    {
        $req = new self();
        $req->page = $page;
        $req->pageSize = $pageSize;
        return $req;
    }
}
