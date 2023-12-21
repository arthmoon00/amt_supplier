<?php

namespace common\components\ATMSupplier\schemas;

class BooleanServiceResponse
{
    /** @var bool  */
    public bool $data;

    /** @var bool  */
    public bool $success;

    /** @var string  */
    public string $message;
}