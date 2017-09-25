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

class PdfQuery extends MutatorAccessible
{
    use MutatorAccessibleAliasesTrait, FillableAttributesTrait;

    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * aliasok a pdf lekérés mezőire
     */
    protected $attributeAliases = [
        'invoiceNumber' => 'szamlaszam',
        'orderNumber' =>'rendelesSzam',
    ];

    /**
     * A pdf lekérés adatainak validálásához használható szabályok
     */
    protected function getQueryValidationRules()
    {
        return [
            'szamlaszam' => ['required' => 'string'],
            'rendelesSzam' => 'string',
        ];
    }
    /**
     * A pdf lekérés adatainak validálása
     * @throws Exception
     */
    public function validateQuery()
    {
        $validator = Validator::make($this->toArray(), $this->getQueryValidationRules());
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return true;
    }

    /**
     * A teljes pdf lekérés validálása
     * @throws Exception
     */
    public function validate()
    {
        return $this->validateQuery();
    }

    /**
     * az xml schemának nem mindegy, hogy milyen sorrendben vannak a key-ek a számlában
     * ez "sorrendbe" rakja őket
     */
    protected function sortAttributes()
    {
        $invoiceKeysOrder = ['felhasznalo', 'jelszo', 'szamlaszam', 'rendelesSzam', 'valaszVerzio'];
        if (isset($this->attributes)) $this->attributes = \sortArrayKeysToOrder($this->attributes, $invoiceKeysOrder);
    }

    public function toArray()
    {
        $this->sortAttributes();
        return $this->attributes;
    }
}
