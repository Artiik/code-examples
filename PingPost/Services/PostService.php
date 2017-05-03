<?php


class PostService
{
    private $sending_url_prod;
    private $sending_url_test;
    private $sending_url;

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
        'FN' => '',
        'LN' => '',
        'S1' => '',
        'CT' => '',
        'SP' => '',
        'HP' => '',
        'EM' => '',
        'HomePhoneConsentLanguage' => 'By clicking below, you authorize QuinStreet and up to four home improvement companies that can help with your project to call you on the phone number provided, and you understand that they may use automated phone technology to call you, and that your consent is not required to purchase products or services.',
    ];

    protected $raw_response;
    protected $response = [
        'status' => null,
        'gdc_key' => null,
        'validation' => null,
    ];

    const PING_STATUS_REJECT = 'REJECTED';
    const PING_STATUS_APPROVED = 'APPROVED';
    protected $contact_ping_status;

    public function __construct(Service $service, $contact_ping_status)
    {
        $this->config = Yii::app()->params['ping_post'];
        $this->sending_url_prod = $this->config['sending_url_prod'];
        $this->sending_url_test = $this->config['sending_url_test'];
        $this->service = $service;
        $this->contact_ping_status = $contact_ping_status;
        $this->setupSettings(\Yii::app()->params['ENV']);
    }

    protected function setupSettings($ENV)
    {
        $this->sending_url = $ENV === self::DEV_ENV ? $this->sending_url_test : $this->sending_url_prod;
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

    protected function prepareReqData($contact)
    {
        $contact_answers = CHtml::listData( $contact->question_answers, 'question_id' , 'value' );
        foreach ($this->service->questions as $question) {
            $this->reqData[$question->url_param_name] = $contact_answers[$question->question_id];
        }
        /////////
        $this->reqData['service'] = $this->service->name;
        $this->reqData['url'] = $contact->url;
        $this->reqData['PC'] = $contact->zip;
        $this->reqData['FN'] = $contact->first_name;
        $this->reqData['LN'] = $contact->last_name;
        $this->reqData['S1'] = $contact->address;
        $this->reqData['CT'] = $contact->city;
        $this->reqData['SP'] = $contact->state;
        $this->reqData['HP'] = $contact->home_phone;
        $this->reqData['EM'] = $contact->email;
        switch ($this->contact_ping_status) {
            case self::PING_STATUS_APPROVED:
                $this->reqData['PingToken'] = $contact->ping_post->ping_token;
                break;
            case self::PING_STATUS_REJECT:

                break;
        }

    }

    public function parseResponse()
    {
        if (stristr($this->raw_response, self::STATUS_SUCCESS)) {
            $pattern = '/GDCKEY:\s*(\d*)/';
            preg_match($pattern, $this->raw_response, $matches, PREG_OFFSET_CAPTURE);
            $this->response['gdc_key'] = $matches[1][0];

            $this->response['status'] = self::STATUS_SUCCESS;
        } elseif (stristr($this->raw_response, self::STATUS_REJECT)) {
            $this->response['status'] = self::STATUS_REJECT;
        } elseif (stristr($this->raw_response, self::STATUS_ERROR)) {
            $this->response['status'] = self::STATUS_ERROR;
        }

        $pattern = '/VALIDATION MESSAGE:\s*?(.*?)/';
        preg_match($pattern, $this->raw_response, $matches, PREG_OFFSET_CAPTURE);
        $this->response['validation'] = $matches[1][0];

        return $this;
    }

    public function getResponse($full = false)
    {
        $response = $this->response;
        if ($full) {
            $response['full_post_request'] = json_encode($this->getReqData());
            $response['full_post_response'] = $this->getRawResponse();
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
}