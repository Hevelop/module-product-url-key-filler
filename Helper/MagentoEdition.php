<?php
/**
 * @author piazzaitalia_hevelop_team
 * @copyright Copyright (c) 2018 Hevelop (https://www.hevelop.com)
 * @package piazzaitalia
 */

namespace Hevelop\ProductUrlKeyFiller\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Helper\Context;

/**
 * Class MagentoEdition
 * @package Hevelop\ProductUrlKeyFiller\Helper
 */
class MagentoEdition extends AbstractHelper
{
    const MAGENTO_ENTERPRISE_EDITION = "Enterprise";

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadataInterface;

    /**
     * MagentoEdition constructor.
     * @param Context $context
     * @param ProductMetadataInterface $productMetadataInterface
     */
    public function __construct(
        Context $context,
        ProductMetadataInterface $productMetadataInterface
    ) {
        $this->productMetadataInterface = $productMetadataInterface;
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isEnterpriseEdition()
    {
        $magentoEdition = $this->productMetadataInterface->getEdition();
        if ($magentoEdition == self::MAGENTO_ENTERPRISE_EDITION) {
            return true;
        } else {
            return false;
        }
    }
}