<?php

declare(strict_types=1);

namespace TTBooking\CastableMoney\Casts;

use InvalidArgumentException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Money\Currency as CurrencyObject;

class Currency implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return new CurrencyObject($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (! $value instanceof CurrencyObject) {
            throw new InvalidArgumentException('Given value is not a Currency instance.');
        }

        return $value->getCode();
    }

    public function serialize($model, string $key, $value, array $attributes)
    {
        return $this->set($model, $key, $value, $attributes);
    }
}
