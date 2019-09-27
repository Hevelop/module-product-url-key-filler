<?php
/**
 * @author piazzaitalia_hevelop_team
 * @copyright Copyright (c) 2018 Hevelop (https://www.hevelop.com)
 * @package piazzaitalia
 */

namespace Hevelop\ProductUrlKeyFiller\Model;

use Hevelop\ProductUrlKeyFiller\Helper\MagentoEdition;
use Hevelop\ProductUrlKeyFiller\Helper\ProductAttributes;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\App\ResourceConnection;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\Framework\Exception\LocalizedException;

class UrlKeyManager
{
    /**
     * @var MagentoEdition
     */
    private $magentoEditionHelper;
    /**
     * @var ProductAttributes
     */
    private $productAttributes;
    /**
     * @var ProductModel
     */
    private $productModel;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var ProductUrlPathGenerator
     */
    private $productUrlPathGenerator;

    /**
     * productManager constructor.
     * @param MagentoEdition $magentoEditionHelper
     * @param ProductAttributes $productAttributes
     * @param ProductModel $productModel
     * @param ResourceConnection $resourceConnection
     * @param ProductUrlPathGenerator $productUrlPathGenerator
     */
    public function __construct(
        MagentoEdition $magentoEditionHelper,
        ProductAttributes $productAttributes,
        ProductModel $productModel,
        ResourceConnection $resourceConnection,
        ProductUrlPathGenerator $productUrlPathGenerator
    ) {
        $this->magentoEditionHelper = $magentoEditionHelper;
        $this->productAttributes = $productAttributes;
        $this->productModel = $productModel;
        $this->resourceConnection = $resourceConnection;
        $this->productUrlPathGenerator = $productUrlPathGenerator;
    }


    /**
     * @param ProductInterface $product
     * @param $output
     * @param bool $onlyEmpty
     * @param array $storeIds
     * @return bool
     * @throws LocalizedException
     */
    public function updateUrlKey(ProductInterface $product, $onlyEmpty = false, $storeIds = [])
    {
        $updated = false;
        $productStoreIds = array_unique(array_merge([0], $product->getStoreIds()));
        foreach ($productStoreIds as $storeId) {
            if (!empty($storeIds) && !in_array($storeId, $storeIds)) {
                continue;
            }
            $urlKey = $this->getValidUrlKey($product, $storeId);
            $currentUrlKey = $this->productAttributes->getAttributeStoreRawValue(
                $product->getId(),
                'url_key',
                $storeId
            );
            if ($onlyEmpty !== true) {
                if ($urlKey != $currentUrlKey) {
                    $this->productAttributes->updateAttributes(
                        [$product->getId()],
                        ['url_key' => $urlKey],
                        $storeId
                    );
                    $updated = true;
                }
            } elseif (empty($currentUrlKey)) {
                $this->productAttributes->updateAttributes(
                    [$product->getId()],
                    ['url_key' => $urlKey],
                    $storeId
                );
                $updated = true;
            }
        }

        return $updated;
    }

    /**
     *  Get valid url key.
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return string
     */
    public function getValidUrlKey(ProductInterface $product, $storeId = 0)
    {
        $name = $this->productAttributes->getAttributeRawValue(
            $product->getId(),
            'name',
            $storeId
        );

        $urlKey = $this->productAttributes->getAttributeRawValue(
            $product->getId(),
            'url_key',
            $storeId
        );
        if ($urlKey === null || $urlKey === false || $urlKey === '') {
            $product->setUrlKey($this->productModel->formatUrlKey($name));
        }

        if (!$this->productUrlKeyExist($product, $storeId)) {
            return $product->getUrlKey();
        }

        $product->setUrlKey($this->productModel->formatUrlKey($name . '-' . $product->getSku()));
        if (!$this->productUrlKeyExist($product, $storeId)) {
            return $product->getUrlKey();
        }

        $product->setUrlKey($this->productModel->formatUrlKey($name . '-' . $product->getSku() . '-' . $storeId));
        return $product->getUrlKey();
    }

    /**
     * Check if product url already exists
     * @param $product
     * @param $storeId
     * @return string
     */
    public function productUrlKeyExist($product, $storeId = null)
    {
        if ($this->urlKeyExists($product, $storeId)
            || $this->requestPathExists($product, $storeId)
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $product
     * @param null $storeId
     * @return bool
     */
    private function urlKeyExists($product, $storeId = null)
    {
        $urlKey = $product->getUrlKey();
        if ($urlKey === false || $urlKey === null || $urlKey === '') {
            return true;
        }

        $tableName = $this->resourceConnection->getTableName('catalog_product_entity_url_key');
        $connection = $this->resourceConnection->getConnection();
        $urlKeyAttrId = $this->productAttributes->getIdByCode('url_key');

        if ($this->magentoEditionHelper->isEnterpriseEdition()) {
            $entityIdField = 'row_id';
            $productId = $product->getRowId();
        } else {
            $entityIdField = 'entity_id';
            $productId = $product->getId();
        }

        $select = $this->resourceConnection->getConnection()->select()
            ->from($tableName, [$entityIdField]);
        if ($productId !== null) {
            $select->where("$entityIdField != ?", $productId);
        }
        $select->where("attribute_id = ?", $urlKeyAttrId);
        if ($storeId !== null) {
            $select->where("store_id = ?", $storeId);
        }
        $select->where("value = ?", $urlKey);
        $select->limit(1);

        $result = $connection->fetchOne($select);

        if ($result === null || $result === '' || $result === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $product
     * @param null $storeId
     * @return bool
     */
    private function requestPathExists($product, $storeId = null)
    {
        $urlKey = $product->getUrlKey();
        if ($urlKey === false || $urlKey === null || $urlKey === '') {
            return true;
        }

        $connection = $this->resourceConnection->getConnection();

        $requestPath = $this->productUrlPathGenerator->getUrlPathWithSuffix($product, $storeId);
        $tableName = $this->resourceConnection->getTableName('url_rewrite');

        $select = $this->resourceConnection->getConnection()->select()
            ->from($tableName, ['entity_id']);

        $select->where("request_path = '$requestPath' OR request_path LIKE '%/$requestPath'");
        if ($storeId !== null) {
            $select->where("store_id = ?", $storeId);
        }

        $select->where("entity_id != ?", $product->getId());

        $select->limit(1);

        $result = $connection->fetchOne($select);

        if ($result === null || $result === '' || $result === false) {
            return false;
        } else {
            return true;
        }
    }
}
