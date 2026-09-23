<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

use Freedex\Agent\Exception\AgentSdkException;

// 新契约拒绝未知属性，避免拼错玩家筛选后意外扩大到全代理范围。
abstract class WhitelistedRequest extends ArraySerializable
{
    protected const FIELDS = [];

    public function toArray(): array
    {
        foreach (get_object_vars($this) as $field => $value) {
            if (!array_key_exists($field, static::FIELDS)) {
                throw new AgentSdkException('unknown request field: ' . $field);
            }
        }
        $out = [];
        foreach (static::FIELDS as $field => $type) {
            $value = $this->{$field};
            if ($value === null || $value === '') {
                continue;
            }
            if (gettype($value) !== $type) {
                throw new AgentSdkException($field . ' has an invalid type');
            }
            $out[$field] = $value;
        }
        return $out;
    }
}
