<?php

namespace common\components\ATMSupplier\schemas;

class OrderLineListServiceResponse
{
    /** @var OrderLine[] */
    public array $data;

    /** @var bool  */
    public bool $success;

    /** @var string  */
    public string $message;
}