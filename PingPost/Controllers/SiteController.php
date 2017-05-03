<?php

Yii::import('application.components.PingPost.PingService');
Yii::import('application.components.PingPost.PostService');

class SiteController extends Controller
{
	  public $url = 'http://leadform.quinstage.com/coreg/hsleadcapture';
	//public $url = 'http://leadpost.net/coreg/hsleadcapture';

	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
			// captcha action renders the CAPTCHA image displayed on the contact page
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xFFFFFF,
			),
			// page action renders "static" pages stored under 'protected/views/site/pages'
			// They can be accessed via: index.php?r=site/page&view=FileName
			'page'=>array(
				'class'=>'CViewAction',
			),
		);
	}

    public function actionPostRequest() {

        $res_data = [];

        $contacts = Contact::model()->with(
            array(
                'ping_post' => array(
                    'joinType' => 'INNER JOIN',
                    'condition' => 'ping_post.status=\''.PostService::STATUS_SUCCESS.'\' AND ping_post.ping_status=\''.PostService::PING_STATUS_APPROVED.'\' AND ping_post.gdc_key IS NULL AND ping_post.is_posted=0',
                ),
            )
        )->findAll();

        foreach ($contacts as $contact) {
            $postService = new PostService(Service::model()->findByPk($contact->service_id), $contact->ping_post->ping_status);
            $post_response = $postService->makeRequest($contact)->parseResponse()->getResponse(true);
            //TODO: maybe decide whether lead is posted in post service based on status.
            $post_response['is_posted'] = 1;
            Contact::updateContactPingPost($post_response, $contact->ping_post->request_id);

            $post_response['contact_id'] = $contact->contact_id;
            $res_data[] = $post_response;
        }

        echo json_encode(['post_responses' => $res_data]);

        ///////////////////////////////////////

    }

	public function actionPostRequestRejected(){

        $res_data = [];

        $contacts = Contact::model()->with(
            array(
                'ping_post' => array(
                    'joinType' => 'INNER JOIN',
                    'condition' => 'ping_post.ping_status=\''.PostService::PING_STATUS_REJECT.'\' AND ping_post.gdc_key IS NULL AND ping_post.is_posted=0',
                ),
            )
        )->findAll();

        foreach ($contacts as $contact) {
            $postService = new PostService(Service::model()->findByPk($contact->service_id), $contact->ping_post->ping_status);
            $post_response = $postService->makeRequest($contact)->parseResponse()->getResponse(true);
            //TODO: maybe decide whether lead is posted in post service based on status.
            $post_response['is_posted'] = 1;
            Contact::updateContactPingPost($post_response, $contact->ping_post->request_id);

            $post_response['contact_id'] = $contact->contact_id;
            $res_data[] = $post_response;
        }

        echo json_encode(['post_responses' => $res_data]);

        ///////////////////////////////////////

	}

	public function actionSendPingRequest() {

        // make ping request
        $pingService = new PingService(Service::model()->findByPk($_POST['service_id']));
        $pingService->makeRequest($_POST)->parseResponse();
        //save contact
        $contact = new Contact();
        $contact->saveContact($_POST, $pingService);
	}
}