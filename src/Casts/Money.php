<?php

declare(strict_types=1);

namespace TTBooking\CastableMoney\Casts;

use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Money\Currency as CurrencyObject;
use Money\Money as MoneyObject;
use ReflectionProperty;
use TTBooking\MoneySerializer\Contracts\SerializesMoney;
use TTBooking\MoneySerializer\Serializers\SimpleMoneySerializer;
use TTBooking\CastableMoney\Exceptions\MoneyCastException;

class Money implements CastsAttributes
{
    /** @var string */
    protected $currencyAttribute;

    /** @var SerializesMoney */
    protected $serializer;

    /**
     * Money caster constructor.
     *
     * @param string $currencyAttribute
     * @param string $serializer
     *
     * @throws MoneyCastException
     */
    public function __construct(
        string $currencyAttribute = 'currency',
        string $serializer = SimpleMoneySerializer::class
    ) {
        $this->currencyAttribute = $currencyAttribute;

        if (! $serializer instanceof SerializesMoney) {
            throw new MoneyCastException('Serializer must implement SerializesMoney contract.');
        }

        try {
            $this->serializer = Container::getInstance()->make($serializer);
        } catch (BindingResolutionException $e) {
            throw new MoneyCastException('Cannot resolve serializer.', 0, $e);
        }
    }

    public function get($model, string $key, $value, array $attributes)
    {
        $currency = static::currency($model->{$this->currencyAttribute});

        return $this->serializer->deserialize($value, $currency);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (! $value instanceof MoneyObject) {
            throw new InvalidArgumentException('Given value is not a Money instance.');
        }

        // Model has writable currency attribute (must accept Currency instance)
        if ($model->hasCast($this->currencyAttribute) || $model->hasSetMutator($this->currencyAttribute)) {
            $model->{$this->currencyAttribute} = $value->getCurrency();

            return $this->serializer->serialize($value);
        }

        // Model has public property or read-only attribute with predefined currency
        elseif (property_exists($model, $this->currencyAttribute) &&
            (new ReflectionProperty($model, $this->currencyAttribute))->isPublic() ||
            $model->hasGetMutator($this->currencyAttribute)) {
            $currency = static::currency($model->{$this->currencyAttribute});
            if (! $value->getCurrency()->equals($currency)) {
                throw new MoneyCastException(sprintf(
                    'Currency mismatch: %s required, %s provided.',
                    $value->getCurrency()->getCode(), $currency->getCode()
                ));
            }

            return $this->serializer->serialize($value);
        }

        // Assume model has writable currency code attribute
        return [
            $key => $this->serializer->serialize($value),
            $this->currencyAttribute => $value->getCurrency()->getCode(),
        ];
    }

    /**
     * @param MoneyObject $money
     *
     * @return string
     */
    /*protected static function serialize(MoneyObject $money): string
    {
        return $money->getAmount();
    }*/

    /**
     * @param string $serialized
     * @param CurrencyObject|null $fallbackCurrency
     *
     * @throws MoneyCastException
     *
     * @return MoneyObject
     */
    /*protected static function deserialize(string $serialized, CurrencyObject $fallbackCurrency = null): MoneyObject
    {
        if (is_null($fallbackCurrency)) {
            throw new MoneyCastException('Fallback currency requested, but not provided.');
        }

        return new MoneyObject($serialized, $fallbackCurrency);
    }*/

    /**
     * @param CurrencyObject|string|null $currency
     *
     * @return CurrencyObject|null
     */
    protected static function currency($currency): ?CurrencyObject
    {
        if (is_null($currency)) {
            return null;
        }

        return $currency instanceof CurrencyObject ? $currency : new CurrencyObject($currency);
    }
}
