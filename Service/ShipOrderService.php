<?php
namespace Mandae\Order\Service;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Model\Order\ShipmentDocumentFactory;

class ShipOrderService
{
    protected $defaultSsaCode;

    private $documentFactory;

    private $objectManager;

    public function __construct (ShipmentDocumentFactory $documentFactory)
    {
        $this->documentFactory = $documentFactory;
        $this->objectManager = ObjectManager::getInstance();
    }

    /**
     * Ship order service
     * 
     * @param OrderInterface $order
     * @return ShipmentInterface
     */
    public function execute (OrderInterface $order): ShipmentInterface
    {
        if (!$order->canShip())
            throw new \Exception(__('Cannot do shipment for the order.'));

        $shipment = $this->documentFactory->create($order);
        $shipment->setSourceCode($order->getDistributionCenter());
        $shipment->register();
        $transaction = $this->objectManager->create(Transaction::class);
        $transaction->addObject($shipment);
        $transaction->addObject($shipment->getOrder());
        $transaction->save();
        return $shipment;
    }
}