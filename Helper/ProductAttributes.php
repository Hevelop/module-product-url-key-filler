<?php
/**
 * @author piazzaitalia_hevelop_team
 * @copyright Copyright (c) 2018 Hevelop (https://www.hevelop.com)
 * @package piazzaitalia
 */

namespace Hevelop\ProductUrlKeyFiller\Helper;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductActions;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\Exception\LocalizedException;

class ProductAttributes
{
    /**
     * @var ProductActions
     */
    private $productAction;
    /**
     * @var Product
     */
    private $productResource;
    /**
     * @var Attribute
     */
    private $eavAttribute;
    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * ProductAttributes constructor.
     * @param ProductActions $productAction
     * @param ProductResource $productResource
     * @param Attribute $eavAttribute
     * @param Config $eavConfig
     */
    public function __construct(
        ProductActions $productAction,
        ProductResource $productResource,
        Attribute $eavAttribute,
        Config $eavConfig
    ) {
        $this->productAction = $productAction;
        $this->productResource = $productResource;
        $this->eavAttribute = $eavAttribute;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Wrap product update attributes
     *
     * @param array $productIds
     * @param array $attrData
     * @param int $storeId
     *
     * @return ProductActions
     */
    public function updateAttributes(array $productIds, array $attrData, $storeId = 0)
    {
        return $this->productAction->updateAttributes($productIds, $attrData, $storeId);
    }

    /**
     * Get Attribute raw value.
     *
     * @param $productId
     * @param $attributeCode
     * @param int $storeId
     *
     * @return mixed
     */
    public function getAttributeRawValue($productId, $attributeCode, $storeId = 0)
    {
        return $this->productResource->getAttributeRawValue($productId, $attributeCode, $storeId);
    }

    /**
     * Get Attribute store raw value
     * avoid fallback on default store value
     * @param $entityId
     * @param $attribute
     * @param $store
     * @return array|bool|mixed
     * @throws LocalizedException
     */
    public function getAttributeStoreRawValue($entityId, $attribute, $store)
    {
        if (!$entityId || empty($attribute)) {
            return false;
        }
        if (!is_array($attribute)) {
            $attribute = [$attribute];
        }

        $attributesData = [];
        $staticAttributes = [];
        $typedAttributes = [];
        $staticTable = null;
        $connection = $this->productResource->getConnection();

        foreach ($attribute as $item) {
            /* @var $attribute \Magento\Catalog\Model\Entity\Attribute */
            $item = $this->getAttribute($item);
            if (!$item) {
                continue;
            }
            $attributeCode = $item->getAttributeCode();
            $attrTable = $item->getBackend()->getTable();
            $isStatic = $item->getBackend()->isStatic();

            if ($isStatic) {
                $staticAttributes[] = $attributeCode;
                $staticTable = $attrTable;
            } else {
                /**
                 * That structure needed to avoid farther sql joins for getting attribute's code by id
                 */
                $typedAttributes[$attrTable][$item->getId()] = $attributeCode;
            }
        }

        /**
         * Collecting static attributes
         */
        if ($staticAttributes) {
            $select = $connection->select()->from(
                $staticTable,
                $staticAttributes
            )->join(
                ['e' => $connection->getTableName($this->productResource->getEntityTable())],
                'e.' . $this->productResource->getLinkField() . ' = ' . $staticTable . '.' . $this->productResource->getLinkField()
            )->where(
                'e.entity_id = ?',
                $entityId
            );
            $attributesData = $connection->fetchRow($select);
        }

        /**
         * Collecting typed attributes, performing separate SQL query for each attribute type table
         */
        if ($store instanceof \Magento\Store\Model\Store) {
            $store = $store->getId();
        }

        $store = (int) $store;
        if ($typedAttributes) {
            foreach ($typedAttributes as $table => $_attributes) {
                $select = $connection->select()
                    ->from(['store_value' => $table], ['attribute_id', 'value'])
                    ->join(
                        ['e' => $connection->getTableName($this->productResource->getEntityTable())],
                        'e.' . $this->productResource->getLinkField() . ' = ' . 'store_value.' . $this->productResource->getLinkField(),
                        ''
                    )
                    ->where('store_value.attribute_id IN (?)', array_keys($_attributes))
                    ->where("e.entity_id = ?", $entityId)
                    ->where('store_value.store_id = ?', $store);


                $result = $connection->fetchPairs($select);
                foreach ($result as $attrId => $value) {
                    $attrCode = $typedAttributes[$table][$attrId];
                    $attributesData[$attrCode] = $value;
                }
            }
        }

        if (is_array($attributesData) && sizeof($attributesData) == 1) {
            $attributesData = array_shift($attributesData);
        }

        return $attributesData === false ? false : $attributesData;
    }

    /**
     * @param $attributeCode
     * @param bool $clear
     * @return AttributeInterface
     * @throws LocalizedException
     */
    public function getAttribute($attributeCode, $clear = false)
    {
        if ($clear) {
            // clear cache before load
            $this->eavConfig->clear();
        }

        /** @var AttributeInterface $attribute */
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        if (!$attribute || !$attribute->getAttributeId()) {
            throw new \RuntimeException(
                __('Attribute with attributeCode "%1" does not exist.', $attributeCode)
            );
        }

        return $attribute;
    }

    public function getIdByCode($attributeCode)
    {
        return $this->eavAttribute->getIdByCode(Product::ENTITY, $attributeCode);
    }
}