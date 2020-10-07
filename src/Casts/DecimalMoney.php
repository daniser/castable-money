<?php

declare(strict_types=1);

namespace TTBooking\CastableMoney\Casts;

use TTBooking\MoneySerializer\Serializers\DecimalMoneySerializer;

class DecimalMoney extends Money
{
    public function __construct(string $currencyAttribute = 'currency')
    {
        parent::__construct($currencyAttribute, DecimalMoneySerializer::class);
    }
}
