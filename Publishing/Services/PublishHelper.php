<?php

/**
 * This class contains helper functions for publishing process.
 *
 */

class PublishHelper
{
    protected $publish_result = [
        'success' => false,
        'stats' => ['count_pub' => 0, 'missed_items' => [], 'overall' => 0],
        'message' => ''
    ];
    protected $params = [];

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public static function checkIfEnoughContent($publish_type, $area_type, array $params)
    {
        $num_of_pages = self::calculateNumOfPagesNeeded($publish_type, $area_type, $params);

        return PagesContent::checkIsEnoughContent($area_type, $num_of_pages);
    }

    public static function calculateNumOfPagesNeeded($publish_type, $area_type, array $params)
    {
        $pages_count = DbPages::getModelByArea($area_type)->countByAttributes(PagesContent::getPagesCondition());

        switch ($publish_type) {
            case 'cities':
                $content_per_page = CatCities::getNumOfCitiesInState($params['state_id']);
                break;
            case 'batch':
                $content_per_page = $params['items_count'];
                break;
            default:
                $content_per_page = 1;
        }
        return ['pages_count' => (int) $pages_count, 'content_per_page' => (int) $content_per_page];
    }

    public static function getNumOfAvailablePublishesPerArea($areas)
    {
        foreach ($areas as &$area) {
            $num_of_pages = self::calculateNumOfPagesNeeded('', $area['area_type'], []);
            $area['num_of_publishes'] = PagesContent::getNumOfPublishes($area['area_type'], $num_of_pages);
        }

        return $areas;
    }

    public static function handleCSV($field_name, callable $callback)
    {
        $csv_file = CUploadedFile::getInstanceByName($field_name);
        if ($csv_file) {
            $file_tmp_name = $csv_file->getTempName();

            if (($handle = fopen($file_tmp_name, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 0, "\r")) !== FALSE) {
                    $callback($data);
                }
                fclose($handle);
            }
        } else {
            $callback(null);
        }
    }

    public function publishBatch($batch)
    {
        $count_pub = 0;
        $missed_items = [];
        foreach ($batch as $item) {
            $item_arr = explode(',', $item);
            // get current item instance
            $area_name = DbPages::AreaTypePluralToSingularMapper($this->params['area_type']);
            $area = $this->getArea($this->params['area_type'], $item_arr[0], $item_arr[1]);

            if ($area) {
                // for every elem in batch check it's published status, if published pass to next item
                //if (self::isPublished($item, $params['area_type'])) {
                if ($area['published'] === '1') {
                    continue;
                }

                // publish element
                $this->params[$area_name . '_id'] = $area[$area_name . '_id'];
                PublisherFactory::make($area_name, $this->params)->run();
                $count_pub++;
            } else {
                $missed_items[] = $item;
            }
        }
        // set results
        $this->publish_result['stats']['count_pub'] = $count_pub;
        $this->publish_result['stats']['overall'] = count($batch);
        $this->publish_result['stats']['missed_items'] = $missed_items;
        $this->setMessage($count_pub);
    }

    public function setMessage($count_pub)
    {
        $stats = $this->publish_result['stats'];
        $message = '';
        if ($count_pub > 0) {
            $this->publish_result['success'] = true;
            $message = "Successful publishing. <br> Results: <br>
                                              Number of published items: {$stats['count_pub']}; <br>
                                              Overall number: {$stats['overall']};";
            if (count($stats['missed_items']) > 0) {
                $message .= '<br> Missed items: ' . join('; ', $stats['missed_items']);
            }
        } else {
            $message = "Nothing to publish.";
        }

        $this->publish_result['message'] = $message;
    }

    public function addMessage($message)
    {
        $this->publish_result['message'] = $message;
    }

    public function getResult()
    {
        return $this->publish_result;
    }

    public static function isPublished($item_name, $area_type)
    {
        $field = DbPages::AreaTypePluralToSingularMapper($area_type) . '_name';
        return DbPages::getModelByArea($area_type)->exists("{$field}=:item_name", array( ':item_name' => $item_name ));
    }

    public static function startProcessLog($params)
    {
        Yii::app()->cache->set('publishing_in_progress', 'process', 7200);
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        Yii::log('[PUBLISHING START]', CLogger::LEVEL_WARNING);
        Yii::log(print_r($params, true), CLogger::LEVEL_WARNING);
    }

    public static function endProcessLog()
    {
        Yii::app()->cache->set('publishing_in_progress', 'completed', 7200);
        Yii::log('[PUBLISHING END]', CLogger::LEVEL_WARNING);
    }

    public function getArea($area_type, $item_name, $state = null)
    {
        $field_name = DbPages::AreaTypePluralToSingularMapper($area_type);
        $model = 'Cat'. ucwords($area_type);

        $query = Yii::app()->db->createCommand()
            ->select('or.*')
            ->from($model::tableName() . ' or')
            ->where($field_name . '_name=:area_name', array(':area_name' => $item_name));

            if (! empty($state)) {
                $query->join('cat_states st', 'st.state_id=or.state_id')
                      ->andWhere('st.state_short=:state_short', array(':state_short' => $state));
            }
            return $query->queryRow();
    }

}
