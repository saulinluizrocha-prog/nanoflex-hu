<?php

//create lead
try{
    $apiConnector = new CApiConnector();

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $data = array(
        'name' => $_POST['name'],
        'phone' => $_POST['phone'],
        'country' => 'HU',//ISO code
        'tz' => 2,

        'region'        => $_POST['region'] ?? null,
        'city'          => $_POST['city'] ?? null,
        'count'         => $_POST['count'] ?? null,
        'address'       => $_POST['address'] ?? null,
        'email'         => $_POST['email'] ?? null,
        'zip'           => $_POST['zip'] ?? null,
        'user_comment'  => $_POST['user_comment'] ?? null,

        'referer'       => $_SERVER['HTTP_REFERER'] ?? null,
        'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'ip'            => $ip,

        'utm_source'    => $_GET['utm_source'] ?? null,
        'utm_medium'    => $_GET['utm_medium'] ?? null,
        'utm_campaign'  => $_GET['utm_campaign'] ?? null,
        'utm_term'      => $_GET['utm_term'] ?? null,
        'utm_content'   => $_GET['utm_content'] ?? null,

        'sub_id'        => $_GET['sub_id'] ?? null,
        'sub_id_1'      => $_GET['sub_id_1'] ?? null,
        'sub_id_2'      => $_GET['sub_id_2'] ?? null,
        'sub_id_3'      => $_GET['sub_id_3'] ?? null,
        'sub_id_4'      => $_GET['sub_id_4'] ?? null,
    );

    $stream_id = '409913';

    if( !empty($stream_id) ){
        $data['stream_id'] = $stream_id;
    }

    $lead = $apiConnector->create($data);

    if( $lead ){
        header('Location: success.html?id='.$lead->id);
    }

}catch (Exception $e) {
    echo $e->getMessage();
}

/*try{
    $apiConnector = new CApiConnector();
    $lead = $apiConnector->extra(array(
        'id' => 'LEAD ID',
        'name' => 'update name',
        'phone' => 'update phone',
        'address' => 'update address',
    ));

    echo "Lead ID #".$lead->id.". Status ".$lead->status;
}catch (Exception $e) {
    echo $e->getMessage();
}*/

/*try{
    $lead_id = 'LEAD ID';
    $apiConnector = new CApiConnector();
    $lead = $apiConnector->status($lead_id);

    echo "Comment #".$lead->comment.". Status ".$lead->status;
}catch (Exception $e) {
    echo $e->getMessage();
}*/


class CApiConnector
{
    public $config = array(
        'api_key' => 'c66289394c2a6e8515c8e8b382fba719',
        'offer_id' => 14858,
        'user_id' => 75329,
        'api_domain' => 'https://t-api.org',
    );

    public function create($params)
    {
        $data = array(
            'name'      => empty($params['name']) ? '' : trim($params['name']),
            'phone'     => empty($params['phone']) ? '' : trim($params['phone']),
            'offer_id'  => $this->config['offer_id'],
            'country'   => empty($params['country']) ? '' : trim($params['country']),
        );

        $not_require_params = array(
            'tz','address','region','city','zip','stream_id','count','email','user_comment',
            'utm_source','utm_medium','utm_campaign','utm_term','utm_content',
            'sub_id','sub_id_1','sub_id_2','sub_id_3','sub_id_4',
            'referer','user_agent','ip','extra_data'
        );

        if( !empty($params) )
        {
            foreach ( $params as $param_key => $param_value )
            {
                if( in_array($param_key, $not_require_params) )
                {
                    $data[$param_key] = $param_value;
                }
            }
        }

        return $this->get_data($data, 'lead', 'create');
    }

    public function extra($params)
    {
        $data = array('id' => $params['id']);

        $not_require_params = array(
            'name','phone','count','zip','address','building','apartment','user_comment',
        );

        if( !empty($params) )
        {
            foreach ( $params as $param_key => $param_value )
            {
                if( in_array($param_key, $not_require_params) )
                {
                    $data[$param_key] = $param_value;
                }
            }
        }

        return $this->get_data($data, 'lead', 'extra');
    }

    public function status($id)
    {
        return $this->get_data(array('id' => $id), 'lead', 'status');
    }

    public function ip()
    {
        return $this->get_data([], 'ip', 'get');
    }

    protected function check_sum($json_data){
        return sha1($json_data . $this->config['api_key']);
    }

    protected function request($data, $model, $method, $headers = array())
    {
        $data = array(
            'user_id' => $this->config['user_id'],
            'data' => $data
        );

        $json_data = json_encode($data);

        $api_url = $this->config['api_domain'].'/api/'.$model.'/'.$method.'?'.http_build_query(array(
                'check_sum' => $this->check_sum($json_data)
            ));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        if( !empty($headers) )
        {
            $http_headers = array();
            foreach( $headers as $header_name => $header_value )
            {
                $http_headers[] = $header_name.': '.$header_value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
        }

        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        return array(
            'error'      => $curl_error,
            'errno'      => $curl_errno,
            'http_code'  => $http_code,
            'result'     => $result,
        );
    }

    protected function get_data($data, $model, $method)
    {
        $response = $this->request($data, $model, $method);

        if( $response['http_code'] == 200 && $response['errno'] === 0 )
        {
            $body = json_decode($response['result']);

            if( json_last_error() === JSON_ERROR_NONE )
            {
                if( $body->status == 'ok' )
                {
                    return $body->data;
                }
                elseif( $body->status == 'error' )
                {
                    throw new Exception($body->error);
                }
                else
                {
                    throw new Exception('Unknown response status');
                }
            }
            else
            {
                throw new Exception('JSON response error');
            }
        }else{
            if( !empty($response['result']) )
            {
                $body = json_decode($response['result']);

                if( json_last_error() === JSON_ERROR_NONE )
                {
                    if( $body->status == 'error' )
                    {
                        throw new Exception($body->error);
                    }
                    else
                    {
                        throw new Exception('Unknown response status');
                    }
                }
                else
                {
                    throw new Exception('JSON response error');
                }
            }
            else
            {
                throw new Exception('HTTP request error. '.$response['error']);
            }
        }
    }
}