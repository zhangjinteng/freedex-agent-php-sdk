<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

class WalletTransferRequest extends WhitelistedRequest
{
    protected const FIELDS = [
        'agentOrderNo' => 'string',
        'agentUserId' => 'string',
        'platformUserId' => 'string',
        'direction' => 'string',
        'currency' => 'string',
        'amount' => 'string',
        'transferAll' => 'boolean',
    ];
    /** @var string|null */
    public $agentOrderNo;
    /** @var string|null */
    public $agentUserId;
    /** @var string|null */
    public $platformUserId;
    /** @var string|null */
    public $direction;
    /** @var string|null */
    public $currency = 'USDT';
    /** @var string|null */
    public $amount;
    /** @var bool|null */
    public $transferAll = false;

    public static function of(string $orderNo, string $userId, string $direction, ?string $amount, bool $transferAll = false): self
    {
        $request = new self();
        $request->agentOrderNo = $orderNo;
        $request->agentUserId = $userId;
        $request->direction = $direction;
        $request->amount = $amount;
        $request->transferAll = $transferAll;
        return $request;
    }

    public static function byPlatformUserId(string $orderNo, string $publicUserId, string $direction, ?string $amount, bool $transferAll = false): self
    {
        $request = self::of($orderNo, '', $direction, $amount, $transferAll);
        $request->platformUserId = $publicUserId;
        return $request;
    }
}
