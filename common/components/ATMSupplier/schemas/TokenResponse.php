<?php

namespace common\components\ATMSupplier\schemas;

class TokenResponse
{
    /** @var string  */
    public string $token;

    /** @var string  */
    public string $message;

    /** @var string  */
    public string $validTo;
}