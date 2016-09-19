<?php

include_once dirname(__FILE__) . '/CallStatisticAbstract.php';

class CallStatisticUIS extends CallStatisticAbstract {

    protected $LOGIN;
    protected $PASSWORD;
    const URL_LOGIN = 'http://universe.uiscom.ru/auth/login/';
    const URL_STAT_ALL = 'http://universe.uiscom.ru/centrex/statistics/cdr_in/get_cdr_in/';
    const URL_STAT_DETAIL = 'http://universe.uiscom.ru/centrex/statistics/trace_cdr/get_trace_cdr/';
    protected $MISSED_CALL_DURATION;
    public $calls_data = array();
    public $errors = array();

    public function __construct($login, $pass, $MISSED_CALL_DURATION)
    {
        parent::__construct();

        $this->LOGIN = $login;
        $this->PASSWORD = $pass;
        $this->MISSED_CALL_DURATION = $MISSED_CALL_DURATION;
    }

    public function getCallList($last_cdr_id = NULL, $LIMIT = 100)
    {
        if (!$this->loginToProvider()) {
            return false;
        }

        $request_data = array('start' => 0, 'limit' => $LIMIT);

        do {
            $calls = array();
            $last_call_not_found = true;

            $calls_request = $this->getPage(self::URL_STAT_ALL, $request_data, array(), false, true);

            if (!isset($calls_request['result']) || $calls_request['result'] != 'ok') {
                $this->addError("Network error. Calls are not received. ".$calls_request['error']."\n");
                return false;
            }

            $json_resp_str = $calls_request['body'];

            $calls_request = $this->getArrayFromJSON($json_resp_str);

            if ($calls_request === false || is_null($calls_request)) {
                $this->addError("Calls data are not valid (JSON: ".$json_resp_str.") \n");
                return false;
            }

            unset($calls_request['rows'][0]);
            $calls = array_merge($calls, $calls_request['rows']);

            if (!is_null($last_cdr_id)) {
                foreach ($calls as $call_key => $call_val) {
                    if ($call_val['id'] == $last_cdr_id) {
                        $calls = array_slice($calls, 0, $call_key);
                        $last_call_not_found = false;
                        break;
                    }
                }
            } else {
                $last_call_not_found = false;
            }

            if (count($calls) > 0) {
                $this->calls_data = array_merge($this->calls_data, $calls);
            }

            if ($last_call_not_found) {
                $request_data['start'] += $LIMIT;
            }

        } while ($last_call_not_found);

        if (count($this->calls_data) > 0) {
            $unfinished_calls = array();
            foreach ($this->calls_data as $call_key => $call_data) {
                $call_detail = $this->getCallDetail($call_data);
                $call_type = $this->getCallType($call_data, $call_detail);
                if ($call_type === 'unfinished_call') {
                    $unfinished_calls[] = $call_key;
                } elseif ($call_type === 'error_call') {
                    $this->calls_data[$call_key]['error_call'] = true;
                }
            }

            if (count($unfinished_calls) > 0) {
                $this->calls_data = array_slice($this->calls_data, (int)end($unfinished_calls) + 1);
            }

            $this->calls_data = array_filter($this->calls_data, function($call) use ($last_cdr_id) {
                return (int)$call['id'] > (int)$last_cdr_id;
            });

            return count($this->calls_data) > 0 ? $this->calls_data : false;
        }
        return false;
    }

    public function getUpdatedCallsData($calls)
    {
        $updated_calls = array();

        foreach ($calls as $call) {

            $request_data = array( 'date_from' => date('Y-m-d H:i:s', strtotime($call['start_time']) - 1), 'sort' => 'start_time', 'limit' => 5);

            $call_request = $this->getPage(self::URL_STAT_ALL, $request_data, array(), false, true);

            if (!isset($call_request['result']) || $call_request['result'] != 'ok') {
                $this->addError("Network error. Missed call ".$call['uis_cdr_id']." are not received. ".$call_request['error']."\n");
            } else {
                $json_resp_str = $call_request['body'];
                $call_request = $this->getArrayFromJSON($json_resp_str);

                if ($call_request === false || is_null($call_request)) {
                    $this->addError("Missed call ".$call['uis_cdr_id']." data are not valid (JSON: ".$json_resp_str.") \n");
                } else {
                    unset($call_request['rows'][0]);
                    foreach ($call_request['rows'] as $call_data) {
                        $resp_call_duration = strtotime($call_data['duration']);
                        $call_duration = strtotime($call['duration']);
                        if ($call_data['id'] === $call['uis_cdr_id'] && ($call_data['is_lost'] === 'False' || $resp_call_duration != $call_duration)) {

                            $call_data['call_id'] = $call['call_id'];
                            $updated_calls[] = $call_data;
                        }
                    }
                }
            }
        }

        return count($updated_calls) > 0 ? $updated_calls : false;
    }

    public function getCallDetail($call)
    {
        $call_id = isset($call['uis_cdr_id']) ? (int)$call['uis_cdr_id'] : (int)$call['id'];
        $call_details = array();

        if (!isset($call['error_call'])) {
            $call_detail_request = $this->getPage(self::URL_STAT_DETAIL, array(
                'start' => 0,
                'limit' => 25,
                'cdr_id' => $call_id
            ), array(), false, true);

            if (!isset($call_detail_request['result']) || $call_detail_request['result'] != 'ok') {
                $this->addError("Details of the call with uis_cdr_id=$call_id are not received.\n");
            } else {
                $json_resp_str = $call_detail_request['body'];
                $call_detail_request = $this->getArrayFromJSON($json_resp_str);

                if ($call_detail_request === false || is_null($call_detail_request)) {
                    $this->addError("Calls details data for call $call_id are not valid (JSON: ".$json_resp_str.").\n");
                } else {
                    $call_detail_request['rows'] = array_filter($call_detail_request['rows'], function($item) {
                        return isset($item['event_name']) && (iconv('utf-8', 'windows-1251', $item['event_name']) === 'Потеряный звонок' ||
                            iconv('utf-8', 'windows-1251', $item['event_name']) === 'Исходящий звонок');
                    });

                    $call_details = $call_detail_request['rows'];
                }
            }
        } else {
            $call_details[] = array(
                'start_time' => $call['start_time'],
                'duration' => "0:00:00",
                'forwarding_duration' => "0:00:00",
                'conversation_duration' => "0:00:00",
                'error_call_detail' => true
            );
        }


        if (count($call_details) > 0) {
            return $call_details;
        }
        return false;
    }

    public function getArrayFromJSON($json_str)
    {
        $json_str = substr($json_str, 1, -1);
        $json_str = iconv('UTF-8', 'UTF-8', $json_str);
        $result_array = json_decode($json_str, true);

        return $result_array;
    }

    public function getCallType($call, $details)
    {
        if ( ( ($call['contact_name'] !== '' && $call['is_lost'] === 'False') ||
                ($call['contact_name'] === '' && $call['is_lost'] === 'True') ||
                ((($call['contact_name'] === '' && $call['is_lost'] === 'False') || ($call['contact_name'] !== '' && $call['is_lost'] === 'True')) && (time() - strtotime($call['start_time'])) > 3600) )
            && $details !== false ) {
            return 'finished_call';
        } else if ( ( ($call['contact_name'] !== '' && $call['is_lost'] === 'False') || ($call['contact_name'] === '' && $call['is_lost'] === 'True') || $call['is_lost'] === '' )
            && $details === false && (time() - strtotime($call['start_time'])) > 3600 ) {
            return 'error_call';
        }
        return 'unfinished_call';
    }

    public function loginToProvider()
    {
        $loginStatRequest = $this->getPage(self::URL_LOGIN,array(),array(
            'login' => $this->LOGIN,
            'password' => $this->PASSWORD
        ));

        if (isset($loginStatRequest['result']) && $loginStatRequest['result'] == 'error') {
            $this->addError("Login failed. ".$loginStatRequest['error']."\n");
            return false;
        }
        return true;
    }

    public function addError($error_text) {
        $this->errors[] = $error_text;
        return true;
    }
} 