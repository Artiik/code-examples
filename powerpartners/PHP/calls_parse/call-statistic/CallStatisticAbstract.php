<?php

/**
 * Class CallStatisticAbstract
 */

abstract class CallStatisticAbstract {

    /**
     * @var null|resource //�������� ���������� ���������� cUrl
     */
    protected $connection = null;

    protected $cookies = array();

    /**
     * ������� ��������� ����������� �� �����.
     *
     * @return mixed // ���������� true ���� ����������� ������ �������, � false ��� ������.
     */
    abstract protected function loginToProvider();

    /**
     * ������� �������� ����� ������ ���� �� ������ �� ���������� � ��.
     * ���� ���������� ������ ���(�� ������, $last_cdr_id = NULL) ����� ���-�� ������� ������ $LIMIT.
     *
     * @param $last_cdr_id // id � ������� uis ���������� ������ �� ��
     * @param int $LIMIT // ���������� ���-�� ������� �� ���� ������
     * @return mixed // ���������� ������ ������� ��� false ���� ��������� ������ ��� ����� ������� ���.
     */
    abstract protected function getCallList($last_cdr_id = NULL, $LIMIT = 100);

    /**
     * �������� ����������� �� ������.
     *
     * @param $call // ������ ������ � ������
     * @return mixed // ���������� ������ ������� ������ ��� false ���� ������ �� �������
     */
    abstract protected function getCallDetail($call);

    /**
     * ��������� ��� ������. �����������, �� ����������� ��� � �� ������� �������.
     *
     * @param $call // ������ ������ � ������
     * @param $details // ������ ������� �� ������
     * @return mixed // ���������� ���� �� ���� ��������: ('finished_call', 'unfinished_call', 'error_call')
     */
    abstract protected function getCallType($call, $details);

    /**
     * ������� ��������� ������ ����������� ������� �� ��, ������ ������ �� �������, � ��������� ���������� �� ��������� ������ � "�����������" �� "��������",
     * ���� ���������� �� ���������� ������ ����������� ������ � �������.
     *
     * @param $calls // ������ ����������� ������� (����. ����� ������: array(call_id, uis_cdr_id, start_time))
     * @return mixed // ���������� ������ ����������� ������� ��� false ���� ������ �� ����������
     */
    abstract protected function getUpdatedCallsData($calls);

    /**
     * ������� ������������ � ������������ JSON ������ � ������������� ������.
     *
     * @param $json_str // JSON ������ ��������� �� ����������
     * @return mixed // ���������� ������������� ������ �� ��������
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
     * ������� ��� cURL.
     * ��� ������������� ����� ������������ JSON ������, ����� � ������ ���� ���������.
     *
     * @param $url //������ ���� ���������� ������
     * @param array $get //������ GET ����������
     * @param array $post //������ POST ����������
     * @param bool $useJSONResponse //true ���� ���������� JSON �����
     * @param bool $useJSONRequest //true ���� ���������� JSON ������
     * @param bool $buildPostQuery //true ��� URL-������������ POST ������ � ������ �������, ������������ � $useJSONRequest
     * @param array $headers //�������������� ���������
     * @return mixed //���������� ���� ������ � ���������
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