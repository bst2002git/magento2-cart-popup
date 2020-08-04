<?php
/**
 * Carousel Source Options
 *
 * @category   Prestafy
 * @package    Prestafy_PopupAdmin
 * @author     Andresa Martins <contact@andresa.dev>
 * @copyright  Copyright (c) 2019 Prestafy eCommerce Solutions (https://www.prestafy.com.br)
 * @license    http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */

namespace Prestafy\PopupAdmin\Model\Config\Source;

class Carousel implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '_bestSellerProducts', 'label' => __('Best Sellers')],
            ['value' => '_latestProducts', 'label' => __('Latest Products')],
            ['value' => '_randomProducts', 'label' => __('Random Products')],
            ['value' => '_upSellingProducts', 'label' => __('Up-Sell Products')],
            ['value' => '_relatedProducts', 'label' => __('Related Products')],
            ['value' => '_crossSellProducts', 'label' => __('Cross-Sell Products')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            '_bestSellerProducts' => __('Best Sellers'),
            '_latestProducts' => __('Latest Products'),
            '_randomProducts' => __('Random Products'),
            '_upSellingProducts' => __('Up-Sell Products'),
            '_relatedProducts' => __('Related Products'),
            '_crossSellProducts' => __('Cross-Sell Products')
        ];
    }
}
