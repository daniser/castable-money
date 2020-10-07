<?php

declare(strict_types=1);

namespace TTBooking\CastableMoney\Casts;

use TTBooking\MoneySerializer\Serializers\JsonMoneySerializer;

class JsonMoney extends Money
{
    public function __construct(string $currencyAttribute = 'currency')
    {
        parent::__construct($currencyAttribute, JsonMoneySerializer::class);
    }
}
