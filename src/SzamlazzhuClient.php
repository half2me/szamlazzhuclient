<?php

namespace Clapp\SzamlazzhuClient;

use Clapp\SzamlazzhuClient\Invoice;
use GuzzleHttp\Psr7\Response;
use LSS\Array2XML;
use GuzzleHttp\Client as HttpClient;
use Clapp\SzamlazzhuClient\Traits\MutatorAccessibleAliasesTrait;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;

/**
 * Created by PhpStorm.
 * User: Creev
 * Date: 2016.03.18.
 * Time: 12:21
 */
class SzamlazzhuClient extends MutatorAccessible
{
    use MutatorAccessibleAliasesTrait;

    protected $apiBase = 'https://www.szamlazz.hu/';

    protected $handler = null;

    protected $attributeAliases = [
        'username' => 'felhasznalo',
        'password' => 'jelszo',
    ];

    /**
     * szamlazz.hu API hívása
     * @param Invoice|StornoInvoice|PdfQuery $data a számlát, sztornó számlát vagy pdf lekérést reprezentáló objektum
     * @return Response szamlazz.hu válasza
     */
    public function request($data)
    {
        $type = get_class($data);
        $data = new $type($data);
        try {
            $data->validate();
        } catch (ValidationException $e) {
            $fieldList = implode(', ', $e->validator->getMessageBag()->keys());
            throw new InvalidArgumentException("invalid data ($fieldList)");
        }

        $data = $this->addRequiredFields($data);

        $httpClientOptions = [
            'base_uri' => $this->apiBase,
            'timeout' => 20.0,
            //'cookies' => true,
        ];
        if ($this->handler !== null) {
            $httpClientOptions['handler'] = $this->handler;
        }

        $client = new HttpClient($httpClientOptions);

        $requestData = $this->transformRequestData($data);
        $response = $client->request('POST', '/szamla/', $requestData);
        return $this->transformResponse($response);
    }

    /**
     * visszaadja a szamlazz.hu API-tól érkező választ, vagy Exceptiont dob, ha ez nem lehetséges.
     * @param $response válasz
     * @return Response válasz
     */
    protected function transformResponse($response)
    {
        $apiErrorCode = array_get($response->getHeader('szlahu_error_code'), 0);
        if (!empty($apiErrorCode) && $apiErrorCode > 0) {

            $apiErrorMessage = array_get($response->getHeader('szlahu_error'), 0);

            throw new SzamlazzhuApiException(
                $apiErrorMessage,
                $apiErrorCode
            );
        }
        return $response;
    }

    /**
     * Hozzáadja a számla elkészítéséhez, számla sztornózásához vagy pdf lekéréséhez szükséges mezőket
     * @param Invoice|StornoInvoice|PdfQuery $data a számlát, sztornó számlát vagy pdf lekérést reprezentáló objektum
     * @return Invoice|StornoInvoice|PdfQuery $data a számlát, sztornó számlát vagy pdf lekérést reprezentáló objektum
     */
    protected function addRequiredFields($data)
    {
        if ($this->username === null || $this->password === null) {
            throw new InvalidArgumentException('missing username and password');
        }

        if (is_a($data, PdfQuery::class)) {
            $data->felhasznalo = $this->username;
            $data->jelszo = $this->password;
            $data->valaszVerzio = 1;
        } else {
            $beallitasok = $data->beallitasok;
            if (empty($beallitasok)) $data->beallitasok = [];

            $beallitasok['felhasznalo'] = $this->username;
            $beallitasok['jelszo'] = $this->password;
            $beallitasok['eszamla'] = false; //„true” ha e-számlát kell készíteni
            $beallitasok['szamlaLetoltes'] = $this->downloadInvoice; //„true” ha a válaszban meg szeretnénk kapni az elkészült PDF számlát
            if (is_a($data, Invoice::class)) {
                $beallitasok['valaszVerzio'] = 1; //1: egyszerű szöveges válaszüzenetet vagy pdf-et ad vissza. 2: xml válasz, ha kérte a pdf-et az base64 kódolással benne van az xml-ben.
            }

            $data->beallitasok = $beallitasok;
        }

        return $data;
    }

    /**
     * Átalakítja az adatokat olyan formátumra, amit az API el tud fogadni
     * @param Invoice|StornoInvoice|PdfQuery $data
     * @return array requestData
     */
    protected function transformRequestData($data)
    {
        switch (get_class($data)) {
            case Invoice::class:
                $type = 'xmlszamla';
                $name = 'action-xmlagentxmlfile';
                break;
            case StornoInvoice::class:
                $type = 'xmlszamlast';
                $name = 'action-szamla_agent_st';
                break;
            case PdfQuery::class:
                $type = 'xmlszamlapdf';
                $name = 'action-szamla_agent_pdf';
                break;
            default:
                throw new InvalidArgumentException('invalid data');
        }

        $body = $this->transformRequestBody($data, $type);

        return [
            'multipart' => [
                [
                    'name' => $name,
                    'contents' => $body,
                    /**
                     * dummy fájlnevet is meg kell adni, különben az API nem foglalkozik a fájllal
                     */
                    'filename' => 'invoice.xml',
                ]
            ]
        ];
    }

    /**
     * Átalakítja a kapott adatot xml-lé, hogy az api is értelmezni tudja
     * @param Invoice|StornoInvoice|PdfQuery $data
     * @return string xml
     */
    protected function transformRequestBody($data, $type)
    {
        $invoiceDocument = Array2XML::createXML(
            $type,
            $data->toArray()
        );
        $node = $invoiceDocument->getElementsByTagName($type)->item(0);
        $node->setAttribute('xmlns', "http://www.szamlazz.hu/{$type}");
        $node->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
        $node->setAttribute('xsi:schemaLocation', "http://www.szamlazz.hu/{$type} {$type}.xsd");
        return $invoiceDocument->saveXML();
    }

    /**
     * teszteléshez GuzzleHttp\HandlerStack beállítása
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;
    }
}
