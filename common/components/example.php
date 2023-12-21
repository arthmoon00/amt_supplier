<?php

namespace common\components;

use common\components\ATMSupplier\AMTSupplier;

$toOrder = [
    [
        'supplier' => "Kia",
        'brand' => "Kia",
        'oem' => "8241238000",
        'quantity' => 2,
        'price' => 4.62,
    ],
    [
        'supplier' => "Toyota",
        'brand' => "Toyota",
        'oem' => "8889922300",
        'quantity' => 1,
        'price' => 0.17, // цена ниже, чем дает поставщик. Должен быть отказ в размещении позиции
    ],
    [
        'supplier' => "Mercedes-Benz",
        'brand' => "Mercedes-Benz",
        'oem' => "1119880578", // по данной позиции поставщик дает замену 1139880278
        'quantity' => 1,
        'price' => 1.99,
    ],
    [
        'supplier' => "Kia",
        'brand' => "Kia",
        'oem' => "8241238001", // товар не существует у поставщика. Должен быть отказ в размещении позиции
        'quantity' => 1,
        'price' => 4.62,
    ],
];

$component = new AMTSupplier(); // компонент, который необходимо реализовать
$result = $component->createOrder($toOrder); // метод, реализующий искомый функционал

var_dump($result);

// var_dump result:
$var_dump_result =
[
    [
        'supplier' => "Kia",
        'brand' => "Kia",
        'oem' => "8241238000",
        'quantity' => 2,
        'price' => 4.62,
        'ordered' => 2, // сколько удалось заказать
        'error' => null,
    ],
    [
        'supplier' => "Toyota",
        'brand' => "Toyota",
        'oem' => "8889922300",
        'quantity' => 1,
        'price' => 0.17, // цена ниже, чем дает поставщик. Должен быть отказ в размещении позиции
        'ordered' => 0, // сколько удалось заказать
        'error' => AMTSupplier::ERROR_PRICE(0.47), // Отказ из-за цены
    ],
    [
        'supplier' => "Mercedes-Benz",
        'brand' => "Mercedes-Benz",
        'oem' => "1119880578", // по данной позиции поставщик дает замену 1139880278
        'quantity' => 1,
        'price' => 1.99,
        'ordered' => 0, // сколько удалось заказать
        'error' => AMTSupplier::ERROR_ANALOG('1139880278'), // Отказ из-за замены детали, по сути - отсутствие товара
    ],
    [
        'supplier' => "Kia",
        'brand' => "Kia",
        'oem' => "8241238001", // товар не существует у поставщика. Должен быть отказ в размещении позиции
        'quantity' => 1,
        'price' => 4.62,
        'ordered' => 0, // сколько удалось заказать
        'error' => AMTSupplier::ERROR_NOT_FOUND(), // Отказ из-за отсутствия товара
    ],
];

