<?php

namespace common\components\ATMSupplier\schemas;

class Offer {
    /** @var Product */
    public Product $product;

    /** @var int */
    public int $price;

    /** @var int */
    public int $availability;

    /** @var bool */
    public bool $availabilityNotAccurate;

    /** @var int */
    public int $minimumOrder;

    /** @var int */
    public int $deliveryTimeInDays;

    /** @var string */
    public string $deliveryDateTime;

    /** @var int */
    public int $quantityInOrder;

    /** @var int */
    public int $stockType;

    /** @var int */
    public int $stockCode;

    /** @var int */
    public int $deliveryCode;

    /** @var string */
    public string $orderRestrictions;
}