<?php

// ALTER TABLE `ps_omniva_int_carrier_country` ADD COLUMN `is_exception` tinyint(1) DEFAULT NULL;
// ALTER TABLE `ps_omniva_int_carrier_country` ADD COLUMN `exception_price` float(10) DEFAULT NULL;

class OmnivaIntCarrierCountry extends ObjectModel
{
    public $id;

    public $id_carrier;

    public $id_country;

    public $price_type;

    public $price;

    public $is_exception;

    public $exception_price;

    public $free_shipping;

    public $cheapest;

    public $tax;

    public $active;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'omniva_int_carrier_country',
        'primary' => 'id',
        'fields' => [
            'id_carrier' =>       ['type' => self::TYPE_INT, 'required' => true, 'size' => 10],
            'id_country' =>       ['type' => self::TYPE_INT, 'required' => true, 'size' => 10],
            'price_type' =>       ['type' => self::TYPE_STRING, 'required' => true, 'size' => 30],
            'price' =>            ['type' => self::TYPE_FLOAT, 'required' => true, 'size' => 10, 'validate' => 'isPrice'],
            'exception_price' =>  ['type' => self::TYPE_FLOAT, 'required' => false, 'size' => 10, 'validate' => 'isPrice'],
            'free_shipping' =>    ['type' => self::TYPE_FLOAT, 'required' => true, 'size' => 10, 'validate' => 'isPrice'],
            'cheapest' =>         ['type' => self::TYPE_BOOL, 'required' => true, 'validate' => 'isBool'],
            'tax' =>              ['type' => self::TYPE_FLOAT, 'required' => true, 'size' => 10],
            'active' =>           ['type' => self::TYPE_BOOL, 'required' => true, 'validate' => 'isBool'],
            'is_exception' =>     ['type' => self::TYPE_BOOL, 'required' => false, 'validate' => 'isBool'],
        ],
    ];

    public static function getCarrierCountry($id_carrier, $id_country)
    {
        $query = (new DbQuery())
            ->select("id")
            ->from(self::$definition['table'])
            ->where('id_carrier = ' . $id_carrier)
            ->where('id_country = ' . $id_country);

        $id_country = Db::getInstance()->getValue($query);
        if (!$id_country) {
            return false;
        }

        return new OmnivaIntCarrierCountry($id_country);
    }
}
