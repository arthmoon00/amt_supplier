<?php

namespace common\components\ATMSupplier\schemas;

class OrderLine
{
    /** @var Product  */
    public Product $product;

    /** @var int */
    public int $quantity;
    public int $amount;
    public int $price;
    public int $actualPrice;
    public int $availability;
    public bool $availabilityNotAccurate;
    public int $minimumOrder;
    public int $deliveryTimeInDays;
    public string $deliveryDateTime;
    public int $stockType;
    public int $stockCode;
    public int $deliveryCode;
    public string $note;
}