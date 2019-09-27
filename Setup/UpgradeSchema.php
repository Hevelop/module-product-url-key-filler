<?php
/**
 * @author piazzaitalia_hevelop_team
 * @copyright Copyright (c) 2018 Hevelop (https://www.hevelop.com)
 * @package piazzaitalia
 */

namespace Hevelop\ProductUrlKeyFiller\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Module\ResourceInterface;

/**
 * Blog update
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    protected $_moduleResource;

    public function __construct(ResourceInterface $moduleResource)
    {
        $this->_moduleResource = $moduleResource;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $version = $context->getVersion();

        $connection = $setup->getConnection();

        if (version_compare($version, '0.2.0', '<')) {
            $urlKeyAttributeId = $connection->fetchOne(
                $connection->select()
                    ->from($setup->getTable('eav_attribute'), ['attribute_id'])
                    ->where('attribute_code = ?', 'url_key')
                    ->where('entity_type_id = ?', 4)
                    ->limit(1)
            );

            $select = $connection->select()
                ->from($setup->getTable('catalog_product_entity_varchar'))
                ->where('attribute_id = ?', $urlKeyAttributeId);
            $connection->query('CREATE VIEW catalog_product_entity_url_key AS ' . $select->__toString());
        }

        $setup->endSetup();
    }
}
