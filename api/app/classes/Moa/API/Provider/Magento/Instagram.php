<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Raja Kapur <raja.kapur@gmail.com>
 * @author Adam Timberlake <adam.timberlake@gmail.com>
 */
trait Instagram {

    /**
     * @method getCustomerModel
     * @return Mage_Customer_Model_Customer
     * @private
     */
    public function getPhotos()
    {
        $collection = array();
        $helper = \Mage::helper('instagramconnect');
        // $websiteId = \Mage::app()->getWebsite()->getId();

        $collection = $helper->getInstagramGalleryImages();
        return Response::json($collection);
    }

}