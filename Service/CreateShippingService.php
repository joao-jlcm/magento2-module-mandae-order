<?php
namespace Mandae\Order\Service;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\Webapi\Rest\Request;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Shipment;
use Mandae\Order\Helper\Data as OrderHelper;

class CreateShippingService
{
    const API_ENDPOINT = '/v2/orders/add-parcel';

    const PRODUCTION_API_URI = 'https://api.mandae.com.br';

    const SANDBOX_API_URI = 'https://sandbox.api.mandae.com.br';

    private $clientFactory;

    private $helper;

    private $sourceRepository;

    public function __construct(
        ClientFactory $clientFactory,
        OrderHelper $helper,
        SourceRepositoryInterface $sourceRepository
    ) {
        $this->clientFactory = $clientFactory;
        $this->helper = $helper;
        $this->sourceRepository = $sourceRepository;
    }

    private function doRequest($token, $requestData): Response
    {
        $client = $this->clientFactory->create([
            'config' => [
                'base_uri' => $this->getBaseUri(),
            ],
        ]);

        try {
            $response = $client->request(
                Request::HTTP_METHOD_POST,
                self::API_ENDPOINT,
                [
                    'headers' => [
                        'Authorization' => $token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $requestData,
                ]
            );
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = (string) $e->getResponse()->getBody();
            $json = json_decode($responseBody, true);
            
            if ($json !== null && isset($json['error']['message']))
                throw new \Exception('API Mandae: ' . $json['error']['message']);
            elseif ($responseBody)
                throw new \Exception('API Mandae: ' . $responseBody);

            throw $e;
        } catch (GuzzleException $e) {
            /*$response = $this->responseFactory->create([
                'status' => $e->getCode(),
                'reason' => $e->getMessage(),
            ]);*/

            throw new \Exception('API Mandae: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Execute service
     * 
     * @param Shipment $shipment
     * @return string tracking id
     */
    public function execute(Shipment $shipment): string
    {
        try {
            $order = $shipment->getOrder();
            $source = $this->getSource($shipment);
            
            $this->doRequest($source->getExtensionAttributes()->getMandaeToken(), [
                //'carrierInvoice' => '',
                'customerId' => $source->getExtensionAttributes()->getMandaeCustomerId(),
                'items' => $this->getItems($source, $order),
                //'observation' => '',
                //'sellerId' => '',
            ]);

            return $source->getExtensionAttributes()->getMandaeTrackingPrefix() . $order->getIncrementId();
        } catch (\Exception $e) {
            $this->helper->log($e->getMessage());
            echo $e->getMessage();exit;
            throw $e;
        }
    }

    protected function getBaseUri()
    {
        return $this->helper->isSandbox() ? self::SANDBOX_API_URI : self::PRODUCTION_API_URI;
    }

    protected function getDimensions(OrderInterface $order)
    {
        return [
            'height' => 0,
            'length' => 0,
            'weight' => 0,
            'width' => 0,
        ];
    }

    protected function getItems(SourceInterface $source, OrderInterface $order)
    {
        $trackingPrefix = $source->getExtensionAttributes()->getMandaeTrackingPrefix();

        return [[
            'channel' => 'ecommerce',
            'dimensions' => $this->getDimensions($order),
            'invoice' => $this->getInvoice($order),
            //'observation' => '',
            'partnerItemId' => $order->getIncrementId(),
            'recipient' => $this->getRecipient($order),
            //'sender' => '',
            'skus' => $this->getSkus($order),
            'shippingService' => str_replace('mandae_', '', $order->getShippingMethod()), // @todo Rápido ou Econômico
            //'store' => $order->getStoreName(), // Nome da loja que realizou a venda
            'totalFreight' => $order->getShippingAmount(), // Valor total do frete
            'totalValue' => $order->getBaseSubtotal(), // Valor total da Nota Fiscal
            'trackingId' => $trackingPrefix . $order->getIncrementId(), // Código de Rastreamento
            //'valueAddedServices' => '',
            //'volumes' => '',
        ]];
    }

    protected function getInvoice(OrderInterface $order)
    {
        $invoice = [
            'id' => null,
            'key' => null,
            'type' => 'NFe',
        ];

        $statusHistory = $order->getStatusHistoryCollection();

        foreach ($statusHistory as $status) {
            if (preg_match('/chave de acesso\s*\:\s*(\d{44})/is', $status->getComment(), $matches)) {
                $invoice['key'] = $matches[1];
                $invoice['id'] = $this->getInvoiceId($invoice['key']);
                break;
            }
        }

        if ($invoice['key'] === null)
            throw new \Exception('Unable to find invoice key.');
        
        return $invoice;
    }

    protected function getInvoiceId($key)
    {
        $id = (int) substr($key, 25, 9);
        $set = (int) substr($key, 22, 3);
        return "$id-$set";
    }

    protected function getRecipient(OrderInterface $order)
    {
        $address = $order->getShippingAddress();

        return [
            'address' => $this->getRecipientAddress($order),
            'document' => preg_replace('/[^0-9]/', '', $order->getCustomerTaxvat()),
            'email' => $address->getEmail(),
            'fullName' => $address->getName(),
            'phone' => preg_replace('/[^0-9]/', '', $address->getTelephone()),
        ];
    }

    protected function getRecipientAddress(OrderInterface $order)
    {
        $address = $order->getShippingAddress();
        
        return [
            'addressLine2' => $address->getStreet()[2], // Complemento
            'city' => $address->getCity(),
            'country' => 'BR',
            'neighborhood' => isset($address->getStreet()[3]) ? $address->getStreet()[3] : '',
            'number' => $address->getStreet()[1],
            'postalCode' => preg_replace('/[^0-9]/', '', $address->getPostcode()),
            //'reference' => '',
            'state' => $address->getRegionCode(),
            'street' => $address->getStreet()[0],
        ];
    }

    protected function getSkus(OrderInterface $order)
    {
        $skus = [];
        $items = $order->getAllItems();

        foreach ($items as $item) {
            $skus[] = [
                'skuId' => $item->getSku(),
                'description' => $item->getName(),
                //'ean' => '',
                'price' => $item->getPrice(),
                'quantity' => (int) $item->getQtyOrdered(),
            ];
        }

        return $skus;
    }

    protected function getSource(Shipment $shipment)
    {
        return $this->sourceRepository->get($shipment->getExtensionAttributes()->getSourceCode());
    }
}