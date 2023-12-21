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
            'email' => 'test@mail.ru',
            'password' => '123123',
            'agreementId' => 1,
            'language' => 'en_US'
        ]
    ],
];
