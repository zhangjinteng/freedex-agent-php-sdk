<?php
declare(strict_types=1);

namespace Freedex\Agent\Http;

interface HttpTransportInterface
{
    /**
     * @param array<string,string> $headers
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): HttpResponse;
}
