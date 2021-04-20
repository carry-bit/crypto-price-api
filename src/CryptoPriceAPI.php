<?php


namespace CryptoPriceAPI;

class ResponseData
{
    private $data = null;

    public function __construct($data = null)
    {
        $this->data = $data;
    }

    public function toPlain()
    {
        return $this->data;
    }

    public function toJson()
    {
        return json_encode($this->data);
    }
}

class CryptoPriceAPI
{
    /*Drivers*/
    private $COIN_MARKET_CAP_DRIVER = "CoinMarketCap";

    private $DEFAULT_CRYPTO_NAME = "bitcoin";

    /*Base URLs*/
    private $baseURL = false;
    private $url = false;
    private $COIN_MARKET_CAP_BASE_URL = "https://www.coinmarketcap.com/currencies/";

    /*DOM*/
    private $HTML_DOM = null;


    /*The website that library using to retrieve crypto prices, CoinMarketCap for example*/
    private $driver;
    private $cryptoName;

    /*The below flags are registered for the result data structure.
    for example $price is registered when we want to get price of crypto,
    or $rank is for market rank of crypto. $price is registered as default*/
    private $price = true;
    private $_24HourLow = false;
    private $_24HourHigh = false;
    private $priceChange = false;
    private $tradingVolume = false;
    private $volume = false;
    private $dominance = false;
    private $rank = false;

    public function __construct($cryptoName = null, $driver = null)
    {
        // Default registration for crypto name. it is btc as default
        $this->cryptoName = ($cryptoName == null) ? $this->cryptoName = $this->DEFAULT_CRYPTO_NAME : $cryptoName;

        // Default registration for driver if class second argument were not registered
        // Default driver is CoinMarketCap because that supports all of the registers
        $this->driver = ($driver == null) ? $this->COIN_MARKET_CAP_DRIVER : $driver;

        // Now we have to set $baseURL attribute using getBaseURL() method
        $this->baseURL = $this->getBaseURL();

        // Then, it's time to make full url, because of that we call getURL() method
        $this->url = $this->getURL();
    }


    /*Methods*/
    public function getBaseURL()
    {
        switch ($this->driver) {
            case $this->COIN_MARKET_CAP_DRIVER:
                return $this->COIN_MARKET_CAP_BASE_URL;

            default:
                return false;
        }
    }

    public function getURL()
    {
        if (!$this->baseURL) return false;

        // combination of full url based on driver is: $baseURL + $cryptoName(based on driver!)
        // and another important tip is that $url is stored as encoded url!
//        return urlencode($this->baseURL . $this->optimizeCryptoNameBasedOnDriver());
        return $this->baseURL . $this->optimizeCryptoNameBasedOnDriver();
    }

    private function optimizeCryptoNameBasedOnDriver()
    {
        if (!$this->baseURL || !$this->cryptoName) return false;

        /*some drivers have particular url.
        actually, we have to optimize $cryptoName based on driver*/
        switch ($this->driver) {
            case $this->COIN_MARKET_CAP_DRIVER:
                // in CoinMarketCap, replace space( ) with dash(-)
                return str_replace(' ', '-', $this->cryptoName);

            default:
                return false;
        }
    }

    public function getData()
    {
        // first we should retrieve HTML_DOM
        if (!$this->retrieveHTML_DOM()) return false;

        // then parse retrieved DOM
        $organizedData = $this->parseDOMContentBasedOnDriver();

        // then based on switches (the boolean attributes of class)
        // make return data
        /*Road map
            1) Price
            2) Price change
            3-1) 24HourLow
            3-2) 24HourHigh
            4) Trading Volume
            5) Volume / Market Cap
            6) Market Dominance
            7) Market Rank
            */
        $returnData = [];

        if ($this->price) $returnData["price"] = $organizedData["price"];

        if ($this->priceChange) $returnData["priceChange"] = $organizedData["priceChange"];

        if ($this->_24HourLow) $returnData["_24HourLow"] = $organizedData["_24HourLow"];

        if ($this->_24HourHigh) $returnData["_24HourHigh"] = $organizedData["_24HourHigh"];

        if ($this->tradingVolume) $returnData["tradingVolume"] = $organizedData["tradingVolume"];

        if ($this->volume) $returnData["marketCap"] = $organizedData["marketCap"];

        if ($this->dominance) $returnData["dominance"] = $organizedData["dominance"];

        if ($this->rank) $returnData["rank"] = $organizedData["rank"];


        return new ResponseData($returnData);
    }

    private function retrieveHTML_DOM()
    {
        if (!$this->url) return false;

        try {
            $this->HTML_DOM = file_get_contents($this->url);

        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }

        if ($this->HTML_DOM) return true;
        else return false;
    }

    private function parseDOMContentBasedOnDriver()
    {
        if (!$this->HTML_DOM) return false;

        $regexPattern = $this->getRegexPattern();
        $extractedFullTable = array();

        preg_match($regexPattern, $this->HTML_DOM, $extractedFullTable);

        if (!$extractedFullTable[1]) return false;

        $organizedArrayData = $this->getOrganizedArrayOfData($extractedFullTable[1]);

        return $organizedArrayData;
    }

    private function getRegexPattern()
    {
        if (!$this->driver) return false;

        /*every driver has particular website structure
        for example we should find a table with specific pattern
        and then extract the data*/
        switch ($this->driver) {
            case $this->COIN_MARKET_CAP_DRIVER:
                return '/<tbody>(.*)<\/tbody>/i';

            default:
                return false;
        }
    }

    private function getOrganizedArrayOfData($rawData)
    {
        if (!$this->driver) return false;

        switch ($this->driver) {
            case $this->COIN_MARKET_CAP_DRIVER:
                return $this->parseCoinMarketCap($rawData);
                break;

            default:
                return false;
        }
    }

    /* DOM Content Parsers */
    private function parseCoinMarketCap($rawData)
    {
        // if extracted data from DOM is not valid...
        if (!$rawData) return false;

        /*Here we extract full content of needed data
        needed data : price, ..., rank*/
        $extractedNeededContent = array();

        preg_match_all('/(<tr((?!<tr).)*?.*?\/tr>)/i', str_replace("</tr>", "</tr>\n", $rawData), $extractedNeededContent);

        // free resources
        $extractedFullTable = null;

        // and separate each of rows in separate index of array
        $extractedNeededContent = array_slice($extractedNeededContent[0], 0, 7);

        $organizedData = [
            "price" => 0.0,
            "priceChange" => 0.0,
            "_24HourLow" => 0.0,
            "_24HourHigh" => 0.0,
            "tradingVolume" => 0.0,
            "marketCap" => 0.0,
            "dominance" => 0.0,
            "rank" => 0
        ];

        foreach ($extractedNeededContent as $index => $rowData) {
            /*Road map
            1) Price
            2) Price change
            3-1) 24HourLow
            3-2) 24HourHigh
            4) Trading Volume
            5) Volume / Market Cap
            6) Market Dominance
            7) Market Rank
            */

            $tempArray = array();

            switch ($index) {
                case 0:
                    // Price:
                    preg_match('/<td>\$(.*)<\/td>/i', $rowData, $tempArray);
                    $organizedData["price"] = ($tempArray[1]) ? floatval(str_replace(',', '', $tempArray[1])) : false;
                    break;

                case 1:
                    // Price Change:
                    preg_match('/<span>\$(.*)<\/span><div>/i', $rowData, $tempArray);
                    $organizedData["priceChange"] = ($tempArray[1]) ? floatval(str_replace(',', '', $tempArray[1])) : false;
                    break;

                case 2:
                    // 24HourLow and 24HourHigh
                    // extracting low and high length in two separated groups
                    preg_match('/<div>\$(.*)<!.*\$(.*)<\/div/i', $rowData, $tempArray);
                    $organizedData["_24HourLow"] = ($tempArray[1]) ? floatval(str_replace(',', '', $tempArray[1])) : false;
                    $organizedData["_24HourHigh"] = ($tempArray[2]) ? floatval(str_replace(',', '', $tempArray[2])) : false;
                    break;

                case 3:
                    // Trading Volume
                    preg_match('/span>\$(.*)<\/span><div/i', $rowData, $tempArray);
                    $organizedData["tradingVolume"] = ($tempArray[1]) ? doubleval(str_replace(',', '', $tempArray[1])) : false;
                    break;

                case 4:
                    // Volume / Market Cap
                    preg_match('/td>(.*)<\/td/i', $rowData, $tempArray);
                    $organizedData["marketCap"] = ($tempArray[1]) ? floatval(str_replace(',', '', $tempArray[1])) : false;
                    break;

                case 5:
                    // Market Dominance
                    preg_match('/span .*>(.*)<!/i', $rowData, $tempArray);
                    $organizedData["dominance"] = ($tempArray[1]) ? floatval(str_replace(',', '', $tempArray[1])) : false;
                    break;

                case 6:
                    // Market Rank
                    preg_match('/td>#(.*)<\/td/i', $rowData, $tempArray);
                    $organizedData["rank"] = ($tempArray[1]) ? floatval(str_replace(',', '', $tempArray[1])) : false;
                    break;
            }
        }

        return $organizedData;
    }

    /*Setter and getters*/

    /**
     * @param bool $price
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }

    /**
     * @param bool $_24HourLow
     */
    public function set24HourLow($_24HourLow)
    {
        $this->_24HourLow = $_24HourLow;
    }

    /**
     * @param bool $24HourHigh
     */
    public function set24HourHigh($_24HourHigh)
    {
        $this->_24HourHigh = $_24HourHigh;
    }

    /**
     * @param bool $priceChange
     */
    public function setPriceChange($priceChange)
    {
        $this->priceChange = $priceChange;
    }

    /**
     * @param bool $tradingVolume
     */
    public function setTradingVolume($tradingVolume)
    {
        $this->tradingVolume = $tradingVolume;
    }

    /**
     * @param bool $volume
     */
    public function setVolume($volume)
    {
        $this->volume = $volume;
    }

    /**
     * @param bool $dominance
     */
    public function setDominance($dominance)
    {
        $this->dominance = $dominance;
    }

    /**
     * @param bool $rank
     */
    public function setRank($rank)
    {
        $this->rank = $rank;
    }

    /**
     * @param string $driver
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;

        // after changing $driver, we have to change $baseURL too
        $this->baseURL = $this->getBaseURL();
    }

    /**
     * @param string $cryptoName
     */
    public function setCryptoName($cryptoName)
    {
        $this->cryptoName = $cryptoName;
    }

    /**
     * @return string
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getCryptoName()
    {
        return $this->cryptoName;
    }

    /**
     * @return null
     */
    public function getHTML_DOM()
    {
        return $this->HTML_DOM;
    }

    // For testing composer package working properly :)
    public static function description()
    {
        return "A library for easy access to the current price of digital currencies.";
    }
}