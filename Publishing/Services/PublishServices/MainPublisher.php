<?php

class MainPublisher extends Publisher {

    protected $page_source_table = 'or';

    public function run()
    {
        return parent::run();
    }

    public function publish()
    {
        DbSeourl::cleanBy('main');
        $source_pages = DbPages::getSourcePages($this->page_source_table);

        foreach ($source_pages as $source_page) {
            $new_page = new DbSeourl();
            $new_page->createNewMain($source_page);
        }
        $this->createSitemap('main');
    }

    public function unPublish()
    {
    }

    public function createSitemap($area)
    {
        $this->createPrimarySitemaps();
    }
}
