<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class VersionResponse
{
    /** @var string */
    public $service = '';
    /** @var string */
    public $version = '';
    /** @var string */
    public $goVersion = '';
    /** @var string */
    public $commit = '';
    /** @var string */
    public $date = '';
}
