<?php

namespace common\components\ATMSupplier\schemas;

class PartnerAgreement
{
    /** @var int  */
    public int $id;

    /** @var string  */
    public string $name;

    /** @var string  */
    public string $partnerName;

    /** @var string  */
    public string $currencyName;

    /** @var string  */
    public string $legalEntityName;
}