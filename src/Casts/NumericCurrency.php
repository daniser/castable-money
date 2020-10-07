<?php

declare(strict_types=1);

namespace TTBooking\CastableMoney\Casts;

use InvalidArgumentException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Money\Currencies\ISOCurrencies;
use Money\Currency as CurrencyObject;

class NumericCurrency implements CastsAttributes
{
    /** @var array<int, string> */
    protected static $currencyMap = [];

    /**
     * NumericCurrency caster constructor.
     */
    public function __construct()
    {
        if (! static::$currencyMap) {
            /** @var ISOCurrencies|CurrencyObject[] $currencies */
            $currencies = new ISOCurrencies;
            foreach ($currencies as $currency) {
                static::$currencyMap[$currencies->numericCodeFor($currency)] = $currency->getCode();
            }
        }
    }

    public function get($model, string $key, $value, array $attributes)
    {
        return new CurrencyObject(static::$currencyMap[$value]);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (! $value instanceof CurrencyObject) {
            throw new InvalidArgumentException('Given value is not a Currency instance.');
        }

        return (new ISOCurrencies)->numericCodeFor($value);
    }
}
