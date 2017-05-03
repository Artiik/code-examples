<?php

interface PublisherInterface {
    public function run();
    public function publish();
    public function unPublish();
    //public function setAreaPublishStatus($area, $status);
//    public function createRedirects();
    public function createSitemap($area);
}
