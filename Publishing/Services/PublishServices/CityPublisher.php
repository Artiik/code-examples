<?php

class CityPublisher extends Publisher {
    public function run()
    {
        return parent::run();
    }

    public function publish()
    {
        $city = CatCities::model()->findByPk($this->params['city_id']);
        $this->setSingleAreaPublishStatus($city, 1);
        $this->publishSingle($city);
        $this->createSitemap($city);
    }

    public function unPublish()
    {
        $city = CatCities::model()->findByPk($this->params['city_id']);
        $this->setSingleAreaPublishStatus($city, 0);
        DbSeourl::cleanBy('city', $city->getPrimaryKey());
        $this->createSitemap($city);
    }

    public function createSitemap($area)
    {
        SiteMap::cleanStateXml($area->state->state_name);
        $this->createStateSitemap($area->state);
        $this->createPrimarySitemaps();
    }
}
