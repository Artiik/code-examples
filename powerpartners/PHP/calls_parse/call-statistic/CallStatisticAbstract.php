<?php

/**
 * Class CallStatisticAbstract
 */

abstract class CallStatisticAbstract {

    /**
     * @var null|resource //Содержит дескриптор соединения cUrl
     */
    protected $connection = null;

    protected $cookies = array();

    /**
     * Функция совершает авторизацию на сайте.
     *
     * @return mixed // Возвращает true если авторизация прошла успешно, и false при ошибке.
     */
    abstract protected function loginToProvider();

    /**
     * Функция получает новые звонки пока не дойдет до последнего в БД.
     * Если последнего звонка нет(БД пустая, $last_cdr_id = NULL) берет кол-во звонков равное $LIMIT.
     *
     * @param $last_cdr_id // id в системе uis последнего звонка из БД
     * @param int $LIMIT // получаемое кол-во звонков за один запрос
     * @return mixed // возвращает массив звонков или false если произошла ошибка или новых звонков нет.
     */
    abstract protected function getCallList($last_cdr_id = NULL, $LIMIT = 100);

    /**
     * Получает детализацию по звонку.
     *
     * @param $call // массив данных о звонке
     * @return mixed // возвращает массив деталей звонка или false если детали не найдены
     */
    abstract protected function getCallDetail($call);

    /**
     * Вычисляет тип звонка. Законченный, не законченный или с не верными данными.
     *
     * @param $call // массив данных о звонке
     * @param $details // массив деталей по звонку
     * @return mixed // возвращает одно из трех значений: ('finished_call', 'unfinished_call', 'error_call')
     */
    abstract protected function getCallType($call, $details);

    /**
     * Функция принимает массив пропущенных звонков из БД, делает запрос по каждому, и преверяет поменялось ли состояние звонка с "пропущенный" на "прянятый",
     * если поменялось то возвращает массив обновленных данных о звонках.
     *
     * @param $calls // массив пропущенных звонков (прим. полей звонка: array(call_id, uis_cdr_id, start_time))
     * @return mixed // возвращает массив обновленных звонков или false если ничего не поменялось
     */
    abstract protected function getUpdatedCallsData($calls);

    /**
     * Функция обрабатывает и конвертирует JSON строку в ассоциативный массив.
     *
     * @param $json_str // JSON строка пришедшая от провайдера
     * @return mixed // возвращает ассоциативный массив со звонками
     */
    abstract protected function getArrayFromJSON($json_str);

    /**
     *
     */
    public function __construct()
    {
        $this->connection = curl_init();
    }

    /**
     * Обертка для cURL.
     * При необходимости можно использовать JSON запрос, ответ и задать свои заголовки.
     *
     * @param $url //адресс куда отправляем запрос
     * @param array $get //массив GET параметров
     * @param array $post //массив POST параметров
     * @param bool $useJSONResponse //true если используем JSON ответ
     * @param bool $useJSONRequest //true если используем JSON запрос
     * @param bool $buildPostQuery //true для URL-кодированния POST данных в строку запроса, используется с $useJSONRequest
     * @param array $headers //дополнительные заголовки
     * @return mixed //возвращает тело ответа и результат
     */
    public function getPage($url, $get = array(), $post = array(), $useJSONResponse = false, $useJSONRequest = false, $buildPostQuery = false, $headers = array())
    {
        $http_headers = array(
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Cache-Control" => "max-age=0",
            "Connection" => "keep-alive",
            "Keep-Alive" => "timeout=5, max=100",
            "Accept-Charset" => "ISO-8859-1,utf-8;q=0.7,*;q=0.3",
            "Accept-Language" => "ru-RU,ru;q=0.8",
            "Pragma" => ""
        );
        if ($useJSONRequest) {
            $http_headers['Content-Type'] = 'application/json; charset=UTF-8';
            $http_headers['X-Requested-With'] = 'XMLHttpRequest';
        }
        if (sizeof($headers) > 0) {
            foreach ($headers as $key=>$val) {
                $http_headers[$key] = $val;
            }
        }

        $getStr = '';
        if (sizeof($get) > 0) {
            foreach ($get as $key=>$val)
                $getStr .= (strlen($getStr)==0 ? '?' : '&').urlencode($key).'='.urlencode($val);
        }
        if (sizeof($post) > 0) {
            curl_setopt($this->connection, CURLOPT_POST, 1);
            if ($useJSONRequest) {
                if ($buildPostQuery)
                    $post = http_build_query($post);
                $post = json_encode($post);
            }
            curl_setopt($this->connection, CURLOPT_POSTFIELDS, $post);
        }
        curl_setopt($this->connection, CURLOPT_URL, $url.$getStr);
        curl_setopt($this->connection, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.97 Safari/537.11');
        curl_setopt($this->connection, CURLOPT_HTTPHEADER, $http_headers);

        curl_setopt($this->connection, CURLOPT_HEADER, true);
        curl_setopt($this->connection, CURLINFO_HEADER_OUT, true);

        if( count($this->cookies) > 0 ){
            $cookieBuffer = array();
            foreach(  $this->cookies as $k=>$c ) $cookieBuffer[] = "$k=$c";
            curl_setopt($this->connection, CURLOPT_COOKIE, implode("; ",$cookieBuffer) );
        }

        curl_setopt($this->connection, CURLOPT_ENCODING, 'gzip,deflate,sdch');
        curl_setopt($this->connection, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->connection, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->connection, CURLOPT_TIMEOUT, 20);

        $res = curl_exec($this->connection);

        if ($res != false) {
            $header_size = curl_getinfo($this->connection, CURLINFO_HEADER_SIZE);
            $header      = substr($res, 0, $header_size);

            preg_match_all("/^Set-cookie: (.*?);/ism", $header, $cookies);
            foreach( $cookies[1] as $cookie ){
                $buffer_explode = strpos($cookie, "=");
                $this->cookies[ substr($cookie,0,$buffer_explode) ] = substr($cookie,$buffer_explode+1);
            }

            $res_body = substr($res, $header_size);

            if ($useJSONResponse)
                $result['body'] = json_decode($res_body);
            else
                $result['body'] = $res_body;
            $result['error'] = '';
            $result['result'] = 'ok';
        } else {
            $result['body'] = '';
            $result['error'] = curl_error($this->connection);
            $result['result'] = 'error';
        }
        return $result;
    }

    /**
     *
     */
    public function close()
    {
        curl_close($this->connection);
    }


} 