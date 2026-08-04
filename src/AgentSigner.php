<?php
declare(strict_types=1);

namespace Freedex\Agent;

class AgentSigner
{
    /**
     * @param array<string,mixed> $params
     */
    public static function buildSignPayload(array $params): string
    {
        unset($params['sign']);
        $filtered = [];
        foreach ($params as $key => $value) {
            $text = self::stringify($value);
            if ($text === '') {
                continue;
            }
            $filtered[$key] = $text;
        }
        ksort($filtered, SORT_STRING);

        $parts = [];
        foreach ($filtered as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode('&', $parts);
    }

    /**
     * @param array<string,mixed> $params
     */
    public static function signParams(array $params, string $apiSecret): string
    {
        return hash_hmac('sha256', self::buildSignPayload($params), $apiSecret);
    }

    public static function signWebhook(string $apiSecret, string $timestamp, string $nonce, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp . $nonce . $rawBody, $apiSecret);
    }

    /**
     * @param mixed $value
     */
    private static function stringify($value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        return (string) $value;
    }
}
