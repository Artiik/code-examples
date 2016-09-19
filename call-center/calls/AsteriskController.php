<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\Contact;
use app\models\User;
use app\models\Call;
use app\models\TempContactsPool;
use app\components\SessionHelper;
use app\components\Filter;
use app\components\Notification;
use app\models\forms\CallForm;
use yii\helpers\BaseJson;

class AsteriskController extends BaseController {

    public $enableCsrfValidation = false;

    const INT_ID_LENGTH = 3;

    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
//                        'ips' => ['127.0.0.1'],
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'getmanagerbycallerid' => ['put'],
                    'answeredoperator' => ['put'],
                    'callstart' => ['put'],
                    'callend' => ['put'],
                    'send-incoming-call' => ['post']
                ],
            ],
        ];
    }

    public function actionGetmanagerbycallerid() {
        $json = file_get_contents('php://input');
        $post = json_decode($json, true);
        $caller_phone = $post['callerid'];
        $call_uniqueid = $post['uniqueid'];
        if (Filter::isPositiveNumber($caller_phone) && Filter::length($caller_phone, 4, 15)) {
            $contact = Contact::getContactByPhone($caller_phone);
            $manager_int_id = null;
            $contact_id = null;
            if ($contact) {
                $manager = $contact->manager;
                $manager_int_id = $manager->int_id;
                $contact_id = $contact->id;
            }
            $online_user_ids = SessionHelper::getOnlineUserIds();
            $online_users = User::getManagerIntIdsByIds($online_user_ids);
            $response = [];
            if ($manager_int_id) {
                $response['responsible'] = strval($manager_int_id);
            }
            $response['free'] = implode(',', $online_users);

            $this->json($response, 200);
        } else {
            $this->json(false, 415, ['callerid' => 'Incorrect data']);
        }
    }

    public function actionAnsweredoperator() {
        $this->json([], 200);
    }

    public function actionSendIncomingCall()
    {
        $phone = Yii::$app->request->post('phone');
        $call_order_script = Yii::$app->params['call_order_script'];
        require_once $call_order_script;

        if (function_exists('call_order')) {
            $user_id = Yii::$app->user->identity->id;
            $user_int_id = Yii::$app->user->identity->int_id;
            $call_order_token = time().$user_id;
            $options = array("external" => $phone, "internal" => $user_int_id, "call_order_token" => $call_order_token);
            $res = call_order($options);

            if ($res[0] == true) {
                $this->json(['call_order_token' => $call_order_token], 200);
            } else {
                $this->json([], 415);
            }
        }

    }

    public function actionCallstart() {
        $call_form = new CallForm();
        $json = file_get_contents('php://input');
        $post = json_decode($json, true);
        $call_form->scenario = CallForm::SCENARIO_CALLSTART;
        $call_form->load($post);
        if ($call_form->validate()) {
            $callerid = $post['callerid'];
            $answered = $post['answered'];
            $call_uniqueid = $post['uniqueid'];
            $manager_int_id = null;
            $contact_id = null;
            $call = new Call();
            if (strlen($callerid) !== self::INT_ID_LENGTH && strlen($answered) !== self::INT_ID_LENGTH) {
                //Входящий звонок
                $contact = Contact::getContactByPhone($callerid);
                $request_params['phone'] = $callerid;
                if ($contact) {
                    $contact_id = $contact->id;
                    $request_params['id'] = $contact_id;
                    $request_params['contact_name'] = implode(' ', array_filter([$contact->surname, $contact->name, $contact->middle_name]));
                }
                Notification::incomingCall($request_params);
                $call->outgoing($call_uniqueid, $contact_id, $callerid);
            } else if (strlen($callerid) == self::INT_ID_LENGTH && strlen($answered) == self::INT_ID_LENGTH) {
                //Внутренний звонок
                $call->incoming($call_uniqueid, $contact_id, $callerid, $answered);
            } else {
                //Исходящий
                $contact = Contact::getContactByPhone($answered);
                $manager_int_id = $callerid;
                if ($contact) {
                    $contact_id = $contact->id;
                }
                $call_order_token = (isset($post['call_order_token']) && $post['call_order_token'] !== 'unknown') ? $post['call_order_token'] : null;
                $call->incoming($call_uniqueid, $contact_id, $answered, $call_order_token);
            }
            $this->json([], 200);
        } else {
            $this->json([], 415, $call_form->getErrors());
        }
    }

    public function actionCallend() {
        $call_form = new CallForm();
        try {
            $json = file_get_contents('php://input');
            $post = json_decode($json, true);
            $call_form->scenario = CallForm::SCENARIO_CALLEND;
            $call_form->load($post);
            if ($call_form->validate()) {
                $uniqueid = $post['uniqueid'];
                $call = Call::getByUniquelId($uniqueid);
                if ($call) {
                    $date_time = $post['datetime'];
                    $status = $post['status'];
                    if ($call->type == Call::CALL_INCOMING) {
                        //Исходящий
                        $managers = $post['callerid'];
                    } else {
                        //Входящий
                        $managers = $post['answered'];
                    }
                    $total_time = null;
                    $answered_time = null;
                    $record_file = null;
                    if ($status == "ANSWERED") {
                        $total_time = $post['totaltime'];
                        $answered_time = $post['answeredtime'];
                        $record_file = $post['record_file'];

                        if (isset($call->contact_id)) {
                            if ($cont_pool = TempContactsPool::findOne(['contact_id' => $call->contact_id])) {
                                $call->tag_id = $cont_pool->tag_id;
                                $cont_pool->delete();
                            }
                        }
                    }
                    if ($call->callEnd($date_time, $total_time, $answered_time, $record_file, $status, $managers)) {
                        $this->json([], 200);
                    }
                    $this->json([], 500);
                } else {
                    $this->json([], 415, ['uniqueid' => 'Not found']);
                }
            } else {
                $this->json([], 415, $call_form->getErrors());
            }
        } catch (\Exception $ex) {
            $this->json([], 500);
        }
    }

}
