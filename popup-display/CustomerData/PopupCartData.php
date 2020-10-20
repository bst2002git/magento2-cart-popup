<?php
/**
 * PopupCart data source
 *
 * @category   Prestafy
 * @package    Prestafy_PopupDisplay
 * @author     Andresa Martins <contact@andresa.dev>
 * @copyright  Copyright (c) 2019 Prestafy eCommerce Solutions (https://www.prestafy.com.br)
 * @license    http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */

namespace Prestafy\PopupDisplay\CustomerData;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Prestafy\PopupDisplay\Helper\Data as Helper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepositoryInterface;

/**
 * PopupCart source
 */
class PopupCartData implements SectionSourceInterface
{
    const CONFIG_PRODUCT_LIMIT = 4;
    const CONFIG_COLLECTION_TYPE = 'cartpopup/settings/product_carousel';

    /**
     * @var Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Magento\Catalog\Helper\Image
     */
    protected $catalogImage;

    /**
     * @var PricingHelper
     */
    protected $pricingHelper;

    /**
     * @var Status
     */
    protected $productStatus;

    /**
     * @var Visibility
     */
    protected $productVisibility;

    /** @var Collection */
    protected $collection;

    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    protected $cartHelper;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var BestSellersCollectionFactory
     */
    protected $bestSellersCollectionFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    protected $productRepository;

    /**
     * @param CollectionFactory $collectionFactory
     * @param Image $catalogImage
     * @param PricingHelper $pricingHelper
     * @param Status $productStatus
     * @param Visibility $productVisibility
     * @param CartHelper $cartHelper
     * @param Helper $helper
     * @param BestSellersCollectionFactory $bestSellersCollectionFactory
     * @param CheckoutSession $checkoutSession
     * @param ProductRepositoryInterface $productRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        Image $catalogImage,
        PricingHelper $pricingHelper,
        Status $productStatus,
        Visibility $productVisibility,
        CartHelper $cartHelper,
        Helper $helper,
        BestSellersCollectionFactory $bestSellersCollectionFactory,
        CheckoutSession $checkoutSession,
        ProductRepositoryInterface $productRepository
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->catalogImage = $catalogImage;
        $this->pricingHelper = $pricingHelper;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->cartHelper = $cartHelper;
        $this->helper = $helper;
        $this->bestSellersCollectionFactory = $bestSellersCollectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->productRepository = $productRepository;

        $this->_initCollection();
    }

    /**
     * Return data for section "cartpopup"
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * {@inheritdoc}
     */
    public function getSectionData()
    {
        $collectionType = $this->helper->getStoreConfig(self::CONFIG_COLLECTION_TYPE);
        $this->$collectionType();
        $output = [
            'cartTotalCount' => $this->cartHelper->getSummaryCount(),
            'products' => $this->_getCollection()
        ];
        return $output;
    }

    /**
     * Init Product Collection
     */
    private function _initCollection()
    {
        /**
         * I'm still using a collection here because currently
         * it is not possible to sort a repository result randomly.
         *
         * TODO: Remove collection and implement a repository
         */

        $this->collection = $this->collectionFactory->create();
        $this->collection->addAttributeToSelect('*');
        $this->collection->addStoreFilter();
        $this->collection->addAttributeToFilter(
            'status',
            ['in' => $this->productStatus->getVisibleStatusIds()]
        );
        $this->collection->addAttributeToFilter(
            'visibility',
            ['in' => $this->productVisibility->getVisibleInSiteIds()]
        );
        $this->collection->addUrlRewrite();
        $this->collection->addMinimalPrice();
    }

    /**
     * Select random products
     */
    private function _randomProducts()
    {
        $this->collection->getSelect()->orderRand();
    }

    /**
     * Select latest products
     */
    private function _latestProducts()
    {
        $this->collection->addAttributeToSort('entity_id', 'desc');
    }

    /**
     * Build Collection
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function _getCollection()
    {
        /**
         * Set collection limit
         * TODO: Create admin configuration to set this value and implement product carousel
         */
        $this->collection->setPageSize(self::CONFIG_PRODUCT_LIMIT);

        foreach ($this->collection as $i => $product) {
            $product->setData('product_url', $product->getProductUrl());
            $product->setData(
                'product_image',
                $this->catalogImage->init($product, 'product_base_image')
                    ->getUrl()
            );
            $product->setData(
                'product_price',
                $this->pricingHelper->currency(
                    $product->getMinimalPrice(),
                    true,
                    false
                )
            );
            $this->collection->removeItemByKey($i);
            $this->collection->addItem($product);
        }

        return $this->collection->toArray();
    }

    /**
     * Create collection with best selling products of this month
     */
    private function _bestSellerProducts()
    {
        $productIds = [];
        $bestSellers = $this->bestSellersCollectionFactory->create()
            ->setPeriod('month');

        foreach ($bestSellers as $product) {
            $productIds[] = $product->getProductId();
        }

        if (empty($productIds)) {
            $this->_randomProducts();
        } else {
            $this->collection->addIdFilter($productIds);
        }
    }

    /**
     * Create collection with UpSelling products
     */
    private function _upSellingProducts()
    {
        $productIds = [];
				if (!empty($this->checkoutSession->getLastAddedProductId())) {
						$product = $this->productRepository->getById($this->checkoutSession->getLastAddedProductId());
						$upSellProducts = $product->getUpSellProducts();

						foreach ($upSellProducts as $upSellProduct) {
								$productIds[] = $upSellProduct->getId();
						}
				}

        if (empty($productIds)) {
            $this->_randomProducts();
        } else {
            $this->collection->addIdFilter($productIds);
        }
    }

    private function _relatedProducts()
    {
        $productIds = [];
				if (!empty($this->checkoutSession->getLastAddedProductId())) {
						$product = $this->productRepository->getById($this->checkoutSession->getLastAddedProductId());
						$relatedProducts = $product->getRelatedProducts();

						foreach ($relatedProducts as $relatedProduct) {
								$productIds[] = $relatedProduct->getId();
						}
				}

        if (empty($productIds)) {
            $this->_randomProducts();
        } else {
            $this->collection->addIdFilter($productIds);
        }
    }

    private function _crossSellProducts()
    {
        $productIds = [];
				if (!empty($this->checkoutSession->getLastAddedProductId())) {
						$product = $this->productRepository->getById($this->checkoutSession->getLastAddedProductId());
						$crossSellProducts = $product->getCrossSellProducts();

						foreach ($crossSellProducts as $crossSellProduct) {
								$productIds[] = $crossSellProduct->getId();
						}
				}

        if (empty($productIds)) {
            $this->_randomProducts();
        } else {
            $this->collection->addIdFilter($productIds);
        }
    }

}
