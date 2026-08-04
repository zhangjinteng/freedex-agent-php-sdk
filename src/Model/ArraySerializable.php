<?php
declare(strict_types=1);

namespace Freedex\Agent\Model;

abstract class ArraySerializable
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $out = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }
}
