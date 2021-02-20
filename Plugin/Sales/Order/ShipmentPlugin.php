<?php
namespace Mandae\Order\Plugin\Sales\Order;

use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\Shipment;
use Mandae\Order\Service\CreateShippingService;

class ShipmentPlugin
{
    protected $createShippingService;

    protected $trackFactory;

    public function __construct(
        CreateShippingService $createShippingService,
        TrackFactory $trackFactory
    ) {
        $this->createShippingService = $createShippingService;
        $this->trackFactory = $trackFactory;
    }

    public function beforeSave(Shipment $shipment)
    {
        $order = $shipment->getOrder();
        
        if (preg_match('/mandae/i', $order->getShippingMethod())) {
            $number = $this->createShippingService->execute($shipment);

            $track = $this->trackFactory->create()->addData([
                'carrier_code' => $order->getShippingMethod(),
                'number' => $number,
                'title' => $order->getShippingDescription(),
            ]);

            $shipment->addTrack($track);
        }
    }
}