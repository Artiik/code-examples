<?php

abstract class Publisher implements PublisherInterface {
    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function run()
    {
        $this->{$this->params['publish_action']}();
        return $this;
    }

    public function publishSingle($area)
    {
        $source_pages = DbPages::getSourcePages($area->page_source_table);

        foreach ($source_pages as $source_page) {
            $new_page = new DbSeourl();
            $new_page->createNew($source_page, $area, isset($this->params['content_type']) ? $this->params['content_type'] : 'original');
        }

        $redirect_pages = DbPages::getRedirectPages($area->page_source_table);

        foreach ($redirect_pages as $redirect_page) {
            DbRedirects::createRedirect($redirect_page, $area);
        }
    }

    public function createStateSitemap($state)
    {
        $sitemap = new SiteMap();
        $sitemap->addState($state, SiteMap::WEEKLY);
        $sitemap->write($sitemap->renderState($sitemap->part1));
        $sitemap->write($sitemap->renderState($sitemap->part2), '', 2);
    }

    public function createPrimarySitemaps()
    {
        $sitemap = new SiteMap();
        $sitemap->addCom(DbSeourl::model()->findAllByAttributes(array('area_id' => 0)), SiteMap::WEEKLY, 0.8);
        $sitemap->write($sitemap->render());
        unset($sitemap);
        $sitemap = new SiteMap();
        $sitemap->createList();
        $sitemap->write($sitemap->render(1));
    }

    public function setSingleAreaPublishStatus($area, $status)
    {
        $default_source_table = DbPages::AreaTypeToPageSourceMapper()[$area->area_name];
        $area->published = $status;
        $area->page_source_table = !empty($this->params['source_table']) ? $this->params['source_table'] : $default_source_table;
        $area->save();
    }

    public function setFullAreaPublishStatus($area_type, $status, $condition = '')
    {
        $source_table = DbPages::AreaTypeToPageSourceMapper()[$area_type];
        $model = 'Cat'. ucwords($area_type);
        $model::model()->updateAll(array('published'=> $status, 'page_source_table' => $source_table), $condition);
    }

    public function getParams()
    {
        return $this->params;
    }
}
