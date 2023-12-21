<?php

namespace common\components\ATMSupplier\schemas;

class OfferListServiceResponse {
    /** @var Offer[] */
    public array $data;

    /** @var bool  */
    public bool $success;

    /** @var string  */
    public string $message;
}