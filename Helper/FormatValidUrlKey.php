<?php
/**
 * @author piazzaitalia_hevelop_team
 * @copyright Copyright (c) 2018 Hevelop (https://www.hevelop.com)
 * @package piazzaitalia
 */

namespace Hevelop\ProductUrlKeyFiller\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Api\Data\ProductInterface;
use Hevelop\ProductUrlKeyFiller\Model\UrlKeyManager;
use Magento\Framework\Stdlib\DateTime\DateTime;

class FormatValidUrlKey extends AbstractHelper {

    /**
     * @var ProductAttributes
     */
    protected $productAttributes;

    /**
     * @var ProductModel
     */
    protected $productModel;

    /**
     * @var UrlKeyManager
     */
    protected $urlKeyManager;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * FormatValidUrlKey constructor.
     * @param Context $context
     * @param ProductAttributes $productAttributes
     * @param ProductModel $productModel
     * @param UrlKeyManager $urlKeyManager
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        ProductAttributes $productAttributes,
        ProductModel $productModel,
        UrlKeyManager $urlKeyManager,
        DateTime $dateTime
    ) {
        $this->productAttributes = $productAttributes;
        $this->productModel = $productModel;
        $this->urlKeyManager = $urlKeyManager;
        $this->dateTime = $dateTime;
        parent::__construct($context);
    }

    /**
     *  Get valid url key.
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return string
     */
    public function formatValidUrlKey(ProductInterface $product, $storeId = null )
    {
        $name = $this->productAttributes->getAttributeRawValue(
            $product->getId(),
            'name',
            $storeId
        );
        if ($name == null || $name == '') {
            $name = $product->getName();
        }

        $urlKey = $this->productAttributes->getAttributeRawValue(
            $product->getId(),
            'url_key',
            $storeId
        );
        if ($urlKey == null || $urlKey == '') {
            $urlKey = $product->getUrlKey();
        }
        

        if ($urlKey === null || $urlKey === false || $urlKey === '') {
            $product->setUrlKey($this->productModel->formatUrlKey($name));
        }

        if (!$this->urlKeyManager->productUrlKeyExist($product, $storeId)) {
            return $product->getUrlKey();
        }

        $dateNow = $this->dateTime->timestamp();
        $product->setUrlKey($this->productModel->formatUrlKey($name . '-' . $dateNow));
        if (!$this->urlKeyManager->productUrlKeyExist($product, $storeId)) {
            return $product->getUrlKey();
        }

        if ($storeId != null) {
            $product->setUrlKey($this->productModel->formatUrlKey($name . '-' . $dateNow . '-' . $storeId));
        }
        return $product->getUrlKey();
    }
}