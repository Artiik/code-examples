<?php

Yii::import('application.components.PublishServices.*');

class PublishController extends Controller {

    public function filters()
    {
        return array(
            'accessControl',
        );
    }

    public function accessRules()
    {
        return array(
            array('allow',
                'users' => array('admin'),
            ),
            array('deny',
                'users' => array('*'),
            ),
            array('deny',
                'users' => array('?'),
            ),
        );
    }

    /**
     * Main action to run publish process
     */
    public function actionRunPublishProcess()
    {
        /*
         * Available publishTypes list:
         * # global
         * # state
         * # cities
         * # city
         * # or_pages
         *
         * Available actions list:
         * # publish
         * # unPublish
         */
        $publish_type   = Yii::app()->request->getParam('publishType');
        $params = [
            'publish_action' => Yii::app()->request->getParam('action'),
            'source_table'   => Yii::app()->request->getParam('source'),
            'state_id'       => Yii::app()->request->getParam('state_id'),
            'city_id'        => Yii::app()->request->getParam('city_id'),
            'content_type'   => Yii::app()->request->getParam('content_type')
        ];

        //$a = Yii::app()->request->urlReferrer;

        PublishHelper::startProcessLog($params);

        PublisherFactory::make($publish_type, $params)->run();

        PublishHelper::endProcessLog();

        //echo CJSON::encode(['result' => 'OK']);
        $this->redirect(Yii::app()->request->urlReferrer);
        //$this->redirect('/admin/re');
    }


    /**
     * Process csv for publishing
     */
    public function actionProcessCSV()
    {
        $area_type   = Yii::app()->request->getParam('area_type');
        $source_pages = Yii::app()->request->getParam('source_pages');
        $content_type = Yii::app()->request->getParam('content_type');

        if (! empty($_FILES)) {
            $publish_params = [
                'publish_action' => 'publish',
                'area_type'      => $area_type,
                'source_table'   => $source_pages,
                'content_type'   => $content_type
            ];

            PublishHelper::startProcessLog($publish_params);

            $pub_process = new PublishHelper($publish_params);
            PublishHelper::handleCSV('csv_file', function($data) use ($pub_process, $area_type, $content_type) {
                if (! empty($data)) {
                    // check if there are enough unique content if needed
                    if ($content_type === 'unique') {
                        if (PublishHelper::checkIfEnoughContent('batch', $area_type, ['items_count' => count($data)])) {
                            $pub_process->publishBatch($data);
                        } else {
                            $pub_process->addMessage("You don't have enough unique content. Make sure you've uploaded some.");
                        }
                    } elseif ($content_type === 'original') {
                        $pub_process->publishBatch($data);
                    }
                } else {
                    $pub_process->addMessage("Nothing to publish.");
                }
            });
        }

        $pub_result = $pub_process->getResult();

        if ($pub_result['success']) {
            Yii::app()->user->setFlash('publish_result', $pub_result['message']);
        }

        PublishHelper::endProcessLog();

        echo CJSON::encode($pub_result);
        //$this->redirect(Yii::app()->request->urlReferrer);
    }
}
