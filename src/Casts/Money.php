<?php

declare(strict_types=1);

namespace TTBooking\CastableMoney\Casts;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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

        if (! is_subclass_of($serializer, SerializesMoney::class)) {
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
        if (is_null($value)) {
            return null;
        }

        $currency = static::currency(data_get($model, $this->currencyAttribute));

        return $this->serializer->deserialize($value, $currency);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        if (! $value instanceof MoneyObject) {
            throw new InvalidArgumentException('Given value is not a Money instance.');
        }

        /** @see https://github.com/laravel/framework/issues/34798 */
        if (__recurring()) {
            return '';
        }

        // Model has writable currency attribute (must accept Currency instance)
        if ($model->hasCast($this->currencyAttribute) || $model->hasSetMutator($this->currencyAttribute)) {
            $model->{$this->currencyAttribute} = $value->getCurrency();

            return $this->serializer->serialize($value);
        }

        // Currency attribute belongs to related model or
        // Model has public property or read-only attribute with predefined currency
        elseif (Str::contains($this->currencyAttribute, '.') ||
            property_exists($model, $this->currencyAttribute) &&
            (new ReflectionProperty($model, $this->currencyAttribute))->isPublic() ||
            $model->hasGetMutator($this->currencyAttribute)) {
            $currency = static::currency(data_get($model, $this->currencyAttribute));
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
     * @param Model $model
     * @param string $key
     * @param float|int $amount
     * @param array $attributes
     *
     * @return mixed
     */
    public function increment($model, string $key, $amount, array $attributes)
    {
        return $this->addOrSubtract($model, $key, $amount, 'add');
    }

    /**
     * @param Model $model
     * @param string $key
     * @param float|int $amount
     * @param array $attributes
     *
     * @return mixed
     */
    public function decrement($model, string $key, $amount, array $attributes)
    {
        return $this->addOrSubtract($model, $key, $amount, 'subtract');
    }

    public function serialize($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        if (! $value instanceof MoneyObject) {
            throw new InvalidArgumentException('Given value is not a Money instance.');
        }

        return $value->jsonSerialize();
    }

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

    /**
     * @param Model $model
     * @param string $key
     * @param float|int $amount
     * @param string $method
     *
     * @return mixed
     */
    protected function addOrSubtract($model, string $key, $amount, string $method)
    {
        if (is_null($model->{$key})) {
            return null;
        }

        return $model->{$key}->{$method}(
            $this->serializer->deserialize((string) $amount, $model->{$key}->getCurrency())
        );
    }
}
