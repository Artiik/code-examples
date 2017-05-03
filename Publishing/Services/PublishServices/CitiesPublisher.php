<?php

class CitiesPublisher extends Publisher {
    protected $area_type = 'cities';

    public function run()
    {
        return parent::run();
    }

    public function publish()
    {
        $state = CatStates::model()->findByPk($this->params['state_id']);

//        $all_state_cities = $state->cities;
//        $non_published_state_cities = $state->cities->where('published = 0');
        $state_cities = $state->cities(['condition' => 'published = 0']);
        $this->setAreaPublishStatus($this->area_type, 1, $state->getPrimaryKey());
        foreach ($state_cities as $city) {
            $this->publishSingle($city);
        }
        $this->createSitemap($state);
    }

    public function unPublish()
    {
        $state = CatStates::model()->findByPk($this->params['state_id']);
        $this->setAreaPublishStatus($this->area_type, 0, $state->getPrimaryKey());
        DbSeourl::cleanBy('cities', $state->getPrimaryKey());
        $this->createSitemap($state);
    }

    public function setAreaPublishStatus($area_type, $status, $state_id)
    {
        $criteria = new CDbCriteria([
            'condition' => 'state_id=:state_id',
            'params' => [':state_id' => $state_id]
        ]);
//        $criteria->condition = 'postID=:postID';
//        $criteria->params = array(':postID'=>10);
        $this->setFullAreaPublishStatus($area_type, $status, $criteria);
    }

    public function createSitemap($area)
    {
        SiteMap::cleanStateXml($area->state_name);
        $this->createStateSitemap($area);
        $this->createPrimarySitemaps();
    }
}
