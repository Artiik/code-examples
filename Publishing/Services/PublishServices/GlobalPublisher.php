<?php

class GlobalPublisher extends Publisher {
    //the areas order must be preserved, because it can break sitemap creation.
    protected $publish_areas = ['cities', 'states'];

    public function run()
    {
        return parent::run();
    }

    public function publish()
    {
        foreach ($this->publish_areas as $area) {
//            if ($area == 'states') {
//                $a = '1';
//            }
            ////////
            $this->publishFullArea($area);
            $this->setFullAreaPublishStatus($area, 1);
        }
    }

    public function unPublish()
    {
        foreach ($this->publish_areas as $area) {
            $this->setFullAreaPublishStatus($area, 0);
        }
        $states = CatStates::model()->findAll();
        foreach ($states as $state) {
            DbSeourl::cleanBy('both', $state->getPrimaryKey());
            SiteMap::cleanStateXml($state->state_name);
        }
        $this->createPrimarySitemaps();
    }

    public function publishFullArea($area)
    {
        $model = 'Cat'. ucwords($area);
        $area_items = $model::model()->findAllByAttributes(['published' => '0']);

        //$area_items = array_slice($area_items, 0, 20);

        foreach($area_items as $item) {
            $this->publishSingle($item);
            $this->createSitemap($item);
        }
        $this->createPrimarySitemaps();
    }

    public function createSitemap($area)
    {
        if ($area->area_name == 'states') {
            $this->createStateSitemap($area);
        }
    }
}
