<?php
declare(strict_types=1);

namespace Freedex\Agent;

class WebhookVerifier
{
    public static function verify(string $apiSecret, string $timestamp, string $nonce, string $rawBody, string $signature): bool
    {
        if (trim($apiSecret) === '' || trim($timestamp) === '' || trim($nonce) === '' || trim($signature) === '') {
            return false;
        }
        $expected = AgentSigner::signWebhook($apiSecret, $timestamp, $nonce, $rawBody);
        return hash_equals($expected, strtolower(trim($signature)));
    }

    public static function idempotencyKey(string $rawBody): string
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return '';
        }
        $orderType = isset($payload['orderType']) ? (string) $payload['orderType'] : '';
        $orderId = isset($payload['orderId']) ? (string) $payload['orderId'] : '';
        $targetStatus = isset($payload['targetStatus']) ? (string) $payload['targetStatus'] : '';
        if ($orderType === '' || $orderId === '' || $targetStatus === '') {
            return '';
        }
        return $orderType . ':' . $orderId . ':' . $targetStatus;
    }
}
