<?php
namespace Mandae\Order\Plugin\InventoryApi;

use Magento\Framework\DataObject;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceSearchResultsInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class SourceRepositoryPlugin
{
    public function afterGet(
        SourceRepositoryInterface $subject,
        SourceInterface $source
    ): SourceInterface {
        return $this->setAttributes($source);
    }

    public function afterGetList(
        SourceRepositoryInterface $subject,
        SourceSearchResultsInterface $sourceSearchResults
    ): SourceSearchResultsInterface {
        $sources = [];
        $items = $sourceSearchResults->getItems();
        
        foreach ($items as $source)
            $sources[] = $this->setAttributes($source);

        $sourceSearchResults->setItems($sources);
        return $sourceSearchResults;
    }

    public function beforeSave(
        SourceRepositoryInterface $subject,
        SourceInterface $source
    ): array {
        if ($source instanceof DataObject) {
            $extensionAttributes = $source->getExtensionAttributes();

            if ($extensionAttributes !== null) {
                $source->setData('mandae_token', $extensionAttributes->getMandaeToken());
                $source->setData('mandae_customer_id', $extensionAttributes->getMandaeCustomerId());
                $source->setData('mandae_tracking_prefix', $extensionAttributes->getMandaeTrackingPrefix());
            }
        }

        return [$source];
    }

    protected function setAttributes(SourceInterface $source)
    {
        $token = $source->getData('mandae_token');
        $customerId = $source->getData('mandae_customer_id');
        $trackingPrefix = $source->getData('mandae_tracking_prefix');
        $extensionAttributes = $source->getExtensionAttributes();
        $extensionAttributes->setMandaeToken($token);
        $extensionAttributes->setMandaeCustomerId($customerId);
        $extensionAttributes->setMandaeTrackingPrefix($trackingPrefix);
        $source->setExtensionAttributes($extensionAttributes);
        return $source;
    }
}