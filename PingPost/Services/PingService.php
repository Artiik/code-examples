<?php


class PingService
{
    private $sending_url_prod;
    private $sending_url_test;
    private $sending_url;

    private $quad_url_default;
    private $quad_url_reject;
    private $quad_url;

    protected $config;

    protected $service;

    const DEV_ENV = 'DEV';
    const PROD_ENV = 'PROD';

    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_REJECT = 'REJECTED';
    const STATUS_ERROR = 'ERROR';

    protected $reqData = [
        'url' => '',
        'PC' => '',
        'HomePhoneType' => '',
        'HomePhoneConsent' => 'Yes',
        'pingType' => 'v2',
    ];

    private $credentials;

    const PING_STATUS_REJECT = 'REJECTED';
    const PING_STATUS_APPROVED = 'APPROVED';
    protected $ping_status;

    protected $raw_response;
    protected $response = [
        'status' => null,
        'ping_token' => null,
        'validation' => null,
        'price' => null,
        'rate_level' => null,
    ];

    public function __construct(Service $service)
    {
        $this->config = Yii::app()->params['ping_post'];
        $this->sending_url_prod = $this->config['sending_url_prod'];
        $this->sending_url_test = $this->config['sending_url_test'];
        $this->quad_url_default = $this->config['quad_url_default'];
        $this->quad_url_reject = $this->config['quad_url_reject'];
        $this->credentials = $this->config['credentials'];
        $this->reqData['url'] = $this->config['quad_url_default'];

        $this->service = $service;
        $this->setupSettings(\Yii::app()->params['ENV']);
    }

    protected function setupSettings($ENV)
    {
        $this->sending_url = $ENV === self::DEV_ENV ? $this->sending_url_test : $this->sending_url_prod;
        if ($ENV === self::DEV_ENV) {
            $this->reqData = array_merge($this->credentials, $this->reqData);
        }
    }

    public function makeRequest($lead_data)
    {
        //TODO: maybe make lead data validation
        $this->prepareReqData($lead_data);

//        $this->raw_response = file_get_contents($this->sending_url, false, stream_context_create(array(
//            'http' => array(
//                'method' => 'POST',
//                'header' => 'Content-type: application/x-www-form-urlencoded',
//                'content' => http_build_query($this->reqData)
//            ))));

        $query_str = http_build_query($this->reqData);
        $this->raw_response = file_get_contents($this->sending_url.'?'.$query_str);

        return $this;
    }

    protected function prepareReqData($data)
    {
        $this->reqData['service'] = $this->service->name;
        foreach ($this->service->questions as $question) {
            $this->reqData[$question->url_param_name] = $data[$question->name];
        }
        ///////
        $this->reqData['PC'] = $data['zip'];
    }

    public function parseResponse()
    {
        $find = array('<BR>', 'MESSAGE');
        $result = str_replace($find, '', $this->raw_response);
        $conten_xml = "<xml>" . $result . "</xml>";
        $xml_pars = simplexml_load_string($conten_xml);
        $find = array('<status>', '</status>', '<STATUS>', '</STATUS>',
            '<ratelevel>', '<RATELEVEL>', '</ratelevel>', '</RATELEVEL>',
            '</VALIDATION>', '<VALIDATION>', '<validation>', '</validation>',
            '<PRICE>', '</PRICE>', '<price>', '</price>',
            '<pingtoken>', '</pingtoken>', '<PINGTOKEN>', '</PINGTOKEN>');
        $to = array('', '', '', '');

        $this->response['status'] = str_replace($find, $to, $xml_pars->STATUS->asXML());
        $this->response['validation'] = str_replace($find, $to, $xml_pars->VALIDATION->asXML());
        $this->response['price'] = intval(str_replace($find, $to, $xml_pars->PRICE->asXML()));
        $this->response['rate_level'] = intval(str_replace($find, $to, $xml_pars->RATELEVEL->asXML()));
        $this->response['ping_token'] = str_replace($find, $to, $xml_pars->PINGTOKEN->asXML());

        if ($this->response['status'] === self::STATUS_REJECT) {
            $this->quad_url = $this->quad_url_reject;
            $this->ping_status = self::PING_STATUS_REJECT;
        } else {
            $this->quad_url = $this->quad_url_default;
            $this->ping_status = self::PING_STATUS_APPROVED;
        }

        return $this;
    }

    public function getResponse($full = false)
    {
        $response = $this->response;
        if ($full) {
            if (! empty($this->ping_status)) {
                $response['ping_status'] = $this->getPingStatus();
            }
            $response['full_ping_request'] = json_encode($this->getReqData());
            $response['full_ping_response'] = $this->getRawResponse();
        }
        return $response;
    }

    public function getRawResponse()
    {
        return $this->raw_response;
    }

    public function getReqData()
    {
        return $this->reqData;
    }

    public function getUrl()
    {
        return $this->reqData['url'];
    }

    public function getQuadUrl()
    {
        return $this->quad_url;
    }

    public function getPingStatus()
    {
        return $this->ping_status;
    }
}