<?php

class InstagramController extends BaseAPIController
{
    public function getPhotos()
    {
        $collection = array()
        $helper = \Mage::getHelper('instagramconnect');
        $collection = $helper->getInstagramGalleryImages();
        return Response::json($collection);
    }
}

?>
