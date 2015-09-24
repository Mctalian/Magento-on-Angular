<?php
namespace Moa\API\Provider\Magento;

use Input;

/**
 * Magento API provider traits for Laravel
 *
 * @author Raja Kapur <raja.kapur@gmail.com>
 * @author Adam Timberlake <adam.timberlake@gmail.com>
 */
trait Product {

    /**
     * Returns product information for one product.
     *
     * @method getProduct
     * @param int $productId
     * @return array
     */
    public function getProduct($productId)
    {
        /** @var \Mage_Catalog_Model_Product $product */
        $product    = \Mage::getModel('catalog/product')->load((int) $productId);

        $products   = array();
        $models     = array();

        if ($product->getTypeId() === 'configurable') {

            $products   = $this->getProductVariations($productId);

            $productIds = array_flatten(array_map(function($product) {
                return $product['id'];
            }, $products['collection']));

            $productIds = array_unique($productIds);

            foreach ($productIds as $productId) {
                array_push($models, $this->getProduct($productId));
            }

        }

        /** @var \Mage_Sendfriend_Model_Sendfriend $friendModel */
        $friendModel = \Mage::getModel('sendfriend/sendfriend');

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockModel */
        $stockModel = \Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        $gallery = array();
        foreach ($product->getMediaGalleryImages() as $image) {
            array_push($gallery, $image->getUrl());
        }  

        $ids = array();
        $categoryIds = (int) $product->getCategoryIds();
        $categoryId  = $categoryIds[0];
        $type        = \Mage::getModel('catalog/category')->load($categoryId);

        foreach ($product->getCategoryIds() as $id) {
            array_push($ids, (int) $id);

            // Add any parent IDs as well.
            $category = \Mage::getModel('catalog/category')->load($id);

            if ($category->parent_id) {
                $parentCategory = \Mage::getModel('catalog/category')->load($category->parent_id);

                if ($parentCategory->parent_id) {
                    array_push($ids, (int) $parentCategory->parent_id);
                }

                array_push($ids, (int) $category->parent_id);
            }
        }

        $attributeSetCollection = \Mage::getResourceModel('eav/entity_attribute_set_collection') ->load();

        $ret = array(
            'id'            => $product->getId(),
            'sku'           => $product->getSku(),
            'name'          => $product->getName(),
            'type'          => $product->getTypeId(),
            "visibility"    => $product->getVisibility(),
            'quantity'      => (int) $stockModel->getQty(),
            'friendUrl'     => $friendModel->canEmailToFriend() ? \Mage::app()->getHelper('catalog/product')->getEmailToFriendUrl($product) : null,
            'weight'     => $product->getData("weight"),
            'price'         => (float) $product->getPrice(),
            'color'        => (int) $product->getData('color'),
            'size'        => (int) $product->getData('size'),
            'manufacturer'  => (int) $product->getData('manufacturer'),
            'description'   => nl2br(trim($product->getDescription())),
            'short_description'   => nl2br(trim($product->getData("short_description"))),
            'largeImage'    => (string) str_replace('localhost', self::IMAGE_PATH, $product->getMediaConfig()->getMediaUrl($product->getData('image'))),
            'gallery'       => $gallery,
            'models'        => $models,
            'similar'       => $product->getRelatedProductIds(),
            'upsell'        => $product->getUpsellProductIds(),
            'crosssell'     => $product->getCrosssellProductIds(),
            "meta_keywords" => $product->getMetaKeyword(),
            "meta_description" => $product->getMetaDescription(),
            "meta_title"    => $product->getMetaTitle(),
            'categories'    => array_unique($ids),
        );

        if (Input::has("populate")){
            $custom_values = explode(",",Input::get("populate"));
            foreach ($custom_values as $i => $value) {
                $products[] = $products[$value] = $ret->getData($value);
            }
        }

        return $ret;
    }

    /**
     * Returns product information for child SKUs of product (colors, sizes, etc).
     * 
     * @method getProductVariations
     * @param int $productId
     * @return array
     */
    public function getProductVariations($productId)
    {
        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product')->load((int) $productId);

        /** @var \Mage_Catalog_Model_Product $children */
        $children = \Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);

        /** @var \Mage_Catalog_Model_Product $attributes */
        $attributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

        $products = array('label' => null, 'collection' => array());

        foreach ($children as $child) {

            foreach ($attributes as $attribute) {

                $products['label'] = $attribute['store_label'];

                foreach ($attribute['values'] as $value) {

                    $childValue = $child->getData($attribute['attribute_code']);

                    if ($value['value_index'] == $childValue) {
                        $products['collection'][] = array(
                            'id'    => (int) $child->getId(),
                            'label' => $value['store_label']
                        );
                    }

                }

            }

        }

        return $products;
    }

    /**
     * @method getProductOptions
     * @param string $attributeName
     * @param bool $processCounts
     * @return string
     */
    public function getProductOptions($attributeName, $processCounts)
    {
        /**
         * @method getCount
         * @param number $value
         * @return int
         */
        $getCount = function ($value) use ($attributeName) {
            $collection = \Mage::getModel('catalog/product')->getCollection();
            $collection->addFieldToFilter(array(array('attribute' => $attributeName, 'eq' => $value)));
            return count($collection);
        };

        $attribute = \Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeName);
        $options   = array();

        if ($attribute->usesSource()) {
            $options = $attribute->getSource()->getAllOptions(false,false);
            $options_admin = $attribute->getSource()->getAllOptions(false,true);
        }

        $response = array();

        foreach ($options as $i=>$option) {

            $current = array(
                'id'    => (int) $option['value'],
                'label' => $option['label'],
                'admin_label' => $options_admin[$i]['label']
            );

            if ($processCounts) {

                // Process the counts if the developer wants them to be!
                $response['count'] = $getCount($option['value']);

            }

            $response[] = $current;

        }

        return $response;
    }

    /**
     * @method getCollectionForCache
     * @param callable $infolog
     * @return array
     */
    public function getCollectionForCache(callable $infolog = null)
    {
        $collection = array();
        $index = 1;

        $products = \Mage::getResourceModel('catalog/product_collection');

        foreach ($products as $product) {
            $productBuilt = $this->getProduct($product->getId());
            if ($productBuilt && ($productBuilt["visibility"]!=1)) $collection[] = $productBuilt; //strip not visible products
            // $collection[] = $productBuilt; //strip not visible products
        }
        return $collection;
    } 

}