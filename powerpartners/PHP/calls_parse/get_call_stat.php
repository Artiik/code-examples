<?php

require_once dirname(__FILE__).'/../config.php';
require_once $ROOT_DIR.'/call-statistic/CallStatisticUIS.php';

ppStart();

$provider = new CallStatisticUIS($UIS_LOGIN, $UIS_PASSWORD, $MISSED_CALL_DURATION);

$res = mysql_query("SELECT MAX(uis_cdr_id) FROM calls_incoming LIMIT 1");
$row = mysql_fetch_array($res, MYSQL_NUM);
$last_cdr_id = $row[0];

$calls = $provider->getCallList($last_cdr_id, $LIMIT = 100);

if ($calls !== false) {
    $calls = prepareCallsData($calls);
    foreach ($calls as $call) {

        $details = $provider->getCallDetail($call);

        if ($details !== false) {
            $details = prepareCallDetailsData($details);
            $call = callAddOperatorIdAndStatus($call, $details);
        }

        // Сохраняем информацию о звонке и его датализацию
        insertCall($call, $details);
    }
}

// Выбираем пропущенные звонки за последние 2 часа
$res = mysql_query("SELECT id AS call_id, uis_cdr_id, start_time, duration
                    FROM calls_incoming
                    WHERE status <> 'ok'
                    AND start_time >= DATE_ADD(NOW(), INTERVAL -2 HOUR)");

$missed_calls = array();
if (mysql_num_rows($res) > 0) {
    while ($row = mysql_fetch_assoc($res)) {
        $missed_calls[] = $row;
    }
}

if (count($missed_calls) > 0) {
    $updated_calls = $provider->getUpdatedCallsData($missed_calls);

    if ($updated_calls !== false) {
        $updated_calls = prepareCallsData($updated_calls);
        foreach ($updated_calls as $updated_call) {

            $updated_call_details = $provider->getCallDetail($updated_call);

            if ($updated_call_details !== false) {
                $updated_call_details = prepareCallDetailsData($updated_call_details);
                $updated_call = callAddOperatorIdAndStatus($updated_call, $updated_call_details);
            }

            // Удаляем старый звонок с деталями
            removeCall($updated_call['call_id']);

            // Сохраняем обновленную информацию о звонке и его детали
            insertCall($updated_call, $updated_call_details);
        }
    }
}


printErrors($provider);

$provider->close();

ppFinish();

function removeCall($id)
{
    mysql_query("DELETE FROM calls_incoming WHERE id = $id");

    mysql_query("DELETE FROM calls_details WHERE call_id = $id");

    return true;
}

function insertCall($call, $details)
{
    mysql_query("INSERT INTO calls_incoming (start_time, phone, site_id, waiting_duration, duration, operator_id, status, uis_cdr_id)
                  VALUES (".$call['start_time'].", ".$call['phone'].", ".$call['site_id'].", ".$call['waiting_duration'].", ".$call['duration'].", ".$call['operator_id'].", ".$call['status'].", ".$call['uis_cdr_id'].")") or die(mysql_error());
    $call_id = mysql_insert_id();

    if ($details !== false) {
        foreach ($details as $detail) {
            mysql_query("INSERT INTO calls_details (call_id, start_time, waiting_duration, duration, operator_id, status)
                          VALUES (".$call_id.", ".$detail['start_time'].", ".$detail['waiting_duration'].", ".$detail['duration'].", ".$detail['operator_id'].", ".$detail['status'].")") or die(mysql_error());
        }
    }

    return true;
}

function prepareCallsData($calls_data)
{
    $prepared_calls_data = array();

    foreach ($calls_data as $call_key => $call_stat) {
        $prepared_calls_data[$call_key]['uis_cdr_id'] = filter_var($call_stat['id'], FILTER_SANITIZE_NUMBER_INT);
        $prepared_calls_data[$call_key]['start_time'] = mysqlStrValue($call_stat['start_time']);
        $prepared_calls_data[$call_key]['duration'] = mysqlStrValue($call_stat['duration']);
        $prepared_calls_data[$call_key]['waiting_duration'] = mysqlStrValue($call_stat['forwarding_duration']);
        $phone = mysqlStrValue($call_stat['ani']);
        $prepared_calls_data[$call_key]['phone'] = $phone;
        if (isset($call_stat['error_call'])) {
            $prepared_calls_data[$call_key]['main_status'] = '"missed"';
            $prepared_calls_data[$call_key]['error_call'] = true;
        } else {
            $prepared_calls_data[$call_key]['main_status'] = $call_stat['is_lost'] === 'True' ? '"missed"' : '"ok"';
        }

        $res = mysql_query("SELECT id FROM sites
                            WHERE phonenum = $phone");

        $site_id = mysql_fetch_assoc($res);
        $site_id = is_null($site_id['id']) ? 'NULL' : $site_id['id'];
        $prepared_calls_data[$call_key]['site_id'] = $site_id;

        isset($call_stat['call_id']) ? $prepared_calls_data[$call_key]['call_id'] = $call_stat['call_id'] : null;
    }

    return $prepared_calls_data;
}

function prepareCallDetailsData($call_details)
{
    $prepared_call_details = array();

    foreach ($call_details as $detail_key => $call_detail) {
        if (!isset($call_detail['error_call_detail'])) {
            $prepared_call_details[$detail_key]['start_time'] = mysqlStrValue($call_detail['start_time']);
            $prepared_call_details[$detail_key]['waiting_duration'] = mysqlStrValue($call_detail['forwarding_duration']);
            $prepared_call_details[$detail_key]['waiting_duration_sec'] = idate('s', strtotime($call_detail['forwarding_duration']));
            $prepared_call_details[$detail_key]['duration'] = mysqlStrValue($call_detail['conversation_duration']);
            $trace_info = mysqlStrValue($call_detail['trace_info']);
            preg_match('/\d{11}/', $trace_info, $matches);
            $phone = $matches[0];

            $res = mysql_query("SELECT id FROM affiliates
                                WHERE phone = $phone AND operator = 1");

            $operator_id = mysql_fetch_assoc($res);
            $operator_id = (int)$operator_id['id'];
            $prepared_call_details[$detail_key]['operator_id'] = $operator_id;

            if (iconv('utf-8', 'windows-1251', $call_detail['event_name']) === 'Потеряный звонок') {
                $prepared_call_details[$detail_key]['status'] = '"missed"';
            } else {
                $prepared_call_details[$detail_key]['status'] = '"ok"';
            }
        } else {
            $prepared_call_details[$detail_key]['start_time'] = $call_detail['start_time'];
            $prepared_call_details[$detail_key]['waiting_duration'] = '"'.$call_detail['forwarding_duration'].'"';
            $prepared_call_details[$detail_key]['waiting_duration_sec'] = idate('s', strtotime($call_detail['forwarding_duration']));
            $prepared_call_details[$detail_key]['duration'] = '"'.$call_detail['conversation_duration'].'"';
            $prepared_call_details[$detail_key]['operator_id'] = 0;
            $prepared_call_details[$detail_key]['status'] = '"missed"';
        }
    }

    return $prepared_call_details;
}

function callAddOperatorIdAndStatus($call, $call_details)
{
    global $MISSED_CALL_DURATION;

    $operator_ids = array();

    foreach ($call_details as $call_detail) {
        if ($call_detail['status'] === '"missed"') {
            if ($call_detail['waiting_duration_sec'] >= $MISSED_CALL_DURATION) {
                !isset($operator_ids['main_missed']) ? $operator_ids['main_missed'] = $call_detail['operator_id'] : null;
            } else {
                $operator_ids['missed'][] = $call_detail['operator_id'];
            }
        } else {
            $operator_ids['ok'][] = $call_detail['operator_id'];
        }
    }

    if ($call['main_status'] == '"missed"') {
        if (isset($operator_ids['main_missed'])) {
            $call['operator_id'] = $operator_ids['main_missed'];
            $call['status'] = '"missed"';
        } else {
            $call['operator_id'] = isset($operator_ids['missed'][0]) ? $operator_ids['missed'][0] : 0;
            $call['status'] = '"runaway"';
        }
    } else {
        $call['operator_id'] = $operator_ids['ok'][0];
        $call['status'] = '"ok"';
    }

    return $call;
}

function mysqlStrValue($value) {
    if (strlen($value)>0) {
        return '"' . mysql_escape_string(trim(iconv('utf-8', 'windows-1251', $value))) . '"';
    } else {
        return 'NULL';
    }
}

function printErrors($provider) {
    if (count($provider->errors) > 0) {
        foreach ($provider->errors as $error) {
            fwrite(STDOUT, $error);
        }
    }
    return true;
}

