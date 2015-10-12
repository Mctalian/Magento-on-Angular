<?php

class InstagramController extends BaseAPIController
{
    public function getPhotos()
    {
    	return Response::json($this->api->getPhotos());
    }
}

?>
