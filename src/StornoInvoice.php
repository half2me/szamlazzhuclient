<?php

namespace Clapp\SzamlazzhuClient;

use InvalidArgumentException;
use Carbon\Carbon;
use Exception;
use Illuminate\Validation\ValidationException;
use Clapp\SzamlazzhuClient\Traits\MutatorAccessibleAliasesTrait;
use Clapp\SzamlazzhuClient\Traits\FillableAttributesTrait;
use Clapp\SzamlazzhuClient\Contract\InvoiceableCustomerContract;
use Clapp\SzamlazzhuClient\Contract\InvoiceableItemCollectionContract;
use Clapp\SzamlazzhuClient\Contract\InvoiceableItemContract;
use Clapp\SzamlazzhuClient\Contract\InvoiceableMerchantContract;

class StornoInvoice extends MutatorAccessible
{
    use MutatorAccessibleAliasesTrait, FillableAttributesTrait;

    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * aliasok a sztornó számla mezőire
     */
    protected $attributeAliases = [
        'customerEmail' => 'vevo.email',

        'merchantEmailReplyto' => 'elado.emailReplyto',
        'merchantEmailSubject' => 'elado.emailTargy',
        'merchantEmailText' => 'elado.emailSzoveg',

        'invoiceNumber' => 'fejlec.szamlaszam',
    ];

    public function setCustomerAttribute($customer){
        if ($customer instanceof InvoiceableCustomerContract){
            $customer = $customer->getInvoiceCustomerData();
        }
        $this->fill($customer);
    }

    public function setMerchantAttribute($customer){
        if ($customer instanceof InvoiceableMerchantContract){
            $customer = $customer->getInvoiceMerchantData();
        }
        $this->fill($customer);
    }
    
    /**
     * dátum mezők
     */
    public function setSignatureDateAttribute($date)
    {
        $date = new Carbon($date);
        array_set($this->attributes, 'fejlec.keltDatum', $date->format('Y-m-d'));
    }
    public function getSignatureDateAttribute()
    {
        $ret = Carbon::now();
        if (array_has($this->attributes, 'fejlec.keltDatum')) {
            $ret = new Carbon(array_get($this->attributes, 'fejlec.keltDatum'));
        }
        return $ret->hour(0)->minute(0)->second(0);
    }
    public function setSettlementDateAttribute($date)
    {
        $date = new Carbon($date);
        array_set($this->attributes, 'fejlec.teljesitesDatum', $date->format('Y-m-d'));
    }
    public function getSettlementDateAttribute()
    {
        $ret = Carbon::now();
        if (array_has($this->attributes, 'fejlec.teljesitesDatum')) {
            $ret = new Carbon(array_get($this->attributes, 'fejlec.teljesitesDatum'));
        }
        return $ret->hour(0)->minute(0)->second(0);
    }

    /**
     * Egy Customer validálásához használható szabályok
     */
    protected function getCustomerValidationRules(){
        return [
            'vevo.email' => 'email',
        ];
    }

    /**
     * Egy Customer validálása
     * @throws Exception
     */
    public function validateCustomer(){
        $validator = Validator::make($this->toArray(), $this->getCustomerValidationRules());
        if ($validator->fails()){
            throw new ValidationException($validator);
        }
        return true;
    }

    /**
     * Egy Merchant validálásához használható szabályok
     */
    protected function getMerchantValidationRules(){
        return [
            'elado.emailReplyto' => 'string',
            'elado.emailTargy' => 'string',
            'elado.emailSzoveg' => 'string',
        ];
    }

    /**
     * Egy Merchant validálása
     * @throws Exception
     */
    public function validateMerchant(){
        $validator = Validator::make($this->toArray(), $this->getMerchantValidationRules());
        if ($validator->fails()){
            throw new ValidationException($validator);
        }
        return true;
    }

    /**
     * A sztornó számla kiegészítő adatainak validálásához használható szabályok
     */
    protected function getOrderDetailsValidationRules()
    {
        return [
            'beallitasok.eszamla' => ['required' => 'boolean'],
            'fejlec.keltDatum' => ['required', 'date:Y-m-d'],
            'fejlec.teljesitesDatum' => ['required', 'date:Y-m-d'],
            'fejlec.szamlaszam' => 'string',
        ];
    }
    /**
     * A sztornó számla kiegészítő adatainak validálása
     * @throws Exception
     */
    public function validateOrderDetails()
    {
        $validator = Validator::make($this->toArray(), $this->getOrderDetailsValidationRules());
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return true;
    }

    /**
     * A teljes sztornó számla validálása
     * @throws Exception
     */
    public function validate()
    {
        return
            $this->validateMerchant() &&
            $this->validateCustomer() &&
            $this->validateOrderDetails();
    }

    /**
     * az xml schemának nem mindegy, hogy milyen sorrendben vannak a key-ek a számlában
     *
     * ez "sorrendbe" rakja őket
     */
    protected function sortAttributes()
    {
        $invoiceKeysOrder = ['beallitasok', 'fejlec', 'elado', 'vevo'];
        $merchantKeysOrder = ['emailReplyto', 'emailTargy', 'emailSzoveg'];
        $settingsKeysOrder = ['felhasznalo', 'jelszo', 'eszamla', 'szamlaLetoltes'];
        $headerKeysOrder = ['szamlaszam', 'keltDatum', 'teljesitesDatum'];

        if (isset($this->attributes)) $this->attributes = \sortArrayKeysToOrder($this->attributes, $invoiceKeysOrder);

        $aliases = [
            'beallitasok' => $settingsKeysOrder,
            'fejlec' => $headerKeysOrder,
            'elado' => $merchantKeysOrder,
        ];

        foreach ($aliases as $name => $keysOrder) {
            if (array_has($this->attributes, $name)) {
                array_set(
                    $this->attributes,
                    $name,
                    \sortArrayKeysToOrder(
                        array_get($this->attributes, $name),
                        $keysOrder
                    )
                );
            }
        }
    }

    public function toArray()
    {
        $this->sortAttributes();
        return $this->attributes;
    }
}
