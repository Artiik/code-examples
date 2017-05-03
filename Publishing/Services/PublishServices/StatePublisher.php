<?php

class StatePublisher extends Publisher {
    public function run()
    {
        return parent::run();
    }

    public function publish()
    {
        $state = CatStates::model()->findByPk($this->params['state_id']);
        $this->setSingleAreaPublishStatus($state, 1);
        $this->publishSingle($state);
        $this->createSitemap($state);
    }

    public function unPublish()
    {
        $state = CatStates::model()->findByPk($this->params['state_id']);
        $this->setSingleAreaPublishStatus($state, 0);
        DbSeourl::cleanBy('state', $state->getPrimaryKey());
        $this->createSitemap($state);
    }

    public function createSitemap($area)
    {
        SiteMap::cleanStateXml($area->state_name);
        $this->createStateSitemap($area);
        $this->createPrimarySitemaps();
    }
}
