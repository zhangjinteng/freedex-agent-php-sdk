<?php
declare(strict_types=1);

namespace Freedex\Agent\Http;

use Freedex\Agent\Exception\AgentSdkException;

class CurlHttpTransport implements HttpTransportInterface
{
    public function send(string $method, string $url, array $headers, ?string $body, float $timeoutSeconds): HttpResponse
    {
        if (!function_exists('curl_init')) {
            throw new AgentSdkException('ext-curl is required');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new AgentSdkException('failed to initialize curl');
        }

        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = $key . ': ' . $value;
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->timeoutMillis($timeoutSeconds));
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeoutMillis($timeoutSeconds));
        if ($headerLines !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }
        if ($body !== null && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new AgentSdkException('agent api request failed: ' . $err);
        }
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new HttpResponse($statusCode, (string) $responseBody);
    }

    private function timeoutMillis(float $timeoutSeconds): int
    {
        if ($timeoutSeconds <= 0) {
            return 0;
        }
        return (int) max(1, round($timeoutSeconds * 1000));
    }
}
