<?php
namespace Mandae\Order\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class Environment implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'sandbox', 'label' => __('Sandbox')],
            ['value' => 'live', 'label' => __('Live')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return ['sandbox' => __('Sandbox'), 'live' => __('Live')];
    }
}
