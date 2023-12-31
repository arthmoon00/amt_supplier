<?php
return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'AMTSupplier' => [
            'class' => \common\components\ATMSupplier\AMTSupplier::class,
            'email' => 'lk@apm.group',
            'password' => '8798633',
            'agreementId' => 628291,
            'currency' => 'EUR',
            'language' => 'en_US',
            'withAddress' => true,
            'deliveryAddress' => [
                "consigneeName" => "APMG Group",
                "company" => "APMG Group",
                "email" => "info@apm.group",
                "phone" => "+971528727000",
                "country" => "UAE",
                "zipCode" => "713073",
                "state" => "Dubai",
                "city" => "Dubai",
                "street" => "Dubai Logistics City",
                "houseNo" => "V5G2+RH",
                "roomNo" => "RH"
            ],
            'desiredShippingDays' => 14
        ]
    ],
];
