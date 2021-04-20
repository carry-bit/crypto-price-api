<?php

require_once __DIR__ . "/../src/CryptoPriceAPI.php";

use CryptoPriceAPI\CryptoPriceAPI;

$cryptoPriceAPI_Object = new CryptoPriceAPI("fuckcoin");

$cryptoPriceAPI_Object->setRank(true);

$organizedData = $cryptoPriceAPI_Object->getData();

print_r(($organizedData) ? $organizedData->toPlain() : "not found");