<?php

declare(strict_types=1);

namespace TTBooking\CastableMoney\Deviators;

use InvalidArgumentException;
use Money\Money as MoneyObject;
use TTBooking\Deviable\Contracts\Deviator;
use TTBooking\MoneySerializer\Contracts\SerializesMoney;

class Money implements Deviator
{
    /** @var SerializesMoney */
    protected $serializer;

    /**
     * Money deviator constructor.
     *
     * @param SerializesMoney $serializer
     */
    public function __construct(SerializesMoney $serializer)
    {
        $this->serializer = $serializer;
    }

    public function increment($operand, $amount)
    {
        if (! $operand instanceof MoneyObject) {
            throw new InvalidArgumentException('Operand should be of type Money.');
        }

        return $operand->add($this->serializer->deserialize((string) $amount, $operand->getCurrency()));
    }

    public function decrement($operand, $amount)
    {
        if (! $operand instanceof MoneyObject) {
            throw new InvalidArgumentException('Operand should be of type Money.');
        }

        return $operand->subtract($this->serializer->deserialize((string) $amount, $operand->getCurrency()));
    }
}
