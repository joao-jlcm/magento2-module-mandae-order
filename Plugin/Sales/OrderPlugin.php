<?php
namespace Mandae\Order\Plugin\Sales;

use Mandae\Order\Service\ShipOrderService;
use Magento\Sales\Model\Order;

class OrderPlugin
{
    protected $service;

    public function __construct(
        ShipOrderService $shipOrderService
    ) {
        $this->service = $shipOrderService;
    }

    public function afterSave(
        Order $order,
        Order $result
    ) {
        $statusHistory = $order->getStatusHistoryCollection();

        if ($order->getStatus() == 'pending') {
            foreach ($statusHistory as $status) {
                if (preg_match('/chave de acesso\s*\:\s*(\d{44})/is', $status->getComment(), $matches)) {
                    $this->service->execute($order);
                    break;
                }
            }
        }

        return $result;
    }
}