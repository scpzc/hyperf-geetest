<?php

namespace Scpzc\HyperfGeetest;


use Hyperf\Guzzle\ClientFactory;
use Psr\Container\ContainerInterface;

/**
 * 极验验证码
 *
 * @package Scpzc\Geetest
 */
class Geetest{

    const GT_SDK_VERSION = 'php_3.0.0';

    private $response,$redis,$guzzleClient;

    public $geetestID, $geetestKey, $config;

    public static $defaultConfig = [
        'width' => '100%',
        'lang' => 'zh-cn',
        'product' => 'popup',
        'clientFailAlert' => '请完成验证码',
        'serverFailAlert' => '验证码校验失败',
    ];

    public function __construct(ContainerInterface $container,ClientFactory $clientFactory){
        $this->container = $container;
        $this->guzzleClient = $clientFactory->create();
        $this->redis = $this->container->get(\Hyperf\Redis\RedisFactory::class)->get('default');
        $this->setConfig([]);
    }

    /**
     * Geetest constructor.
     *
     * @param array $config
     */
    public function setConfig($config)
    {
        $config = array_merge(self::$defaultConfig, $config);
        $this->geetestID = config('geetest.geetest_id');
        $this->geetestKey = config('geetest.geetest_key');
        $this->config = $config;
    }

    /**
     * 判断极验服务器是否down机
     *
     * @param $param
     * @param int $newCaptcha
     * @return int
     */
    private function preProcess($param, $newCaptcha=1) {
        $data = array(
            'gt'=>$this->geetestID,
            'newCaptcha'=>$newCaptcha
        );
        $data = array_merge($data,$param);
        $query = http_build_query($data);
        $url = "http://api.geetest.com/register.php?" . $query;
        $client = $this->guzzleClient;
        $response = $client->get($url);
        $response = $response->getBody()->getContents();
        if (strlen($response) != 32) {
            $this->failbackProcess();
            return 0;
        }
        $this->successProcess($response);
        return 1;
    }

    /**
     * 成功回调
     *
     * @param $challenge
     */
    private function successProcess($challenge) {
        $challenge = md5($challenge.$this->geetestKey);
        $result = [
            'success'    => 1,
            'gt'         => $this->geetestID,
            'challenge'  => $challenge,
            'newCaptcha' => 1
        ];
        $this->response = $result;
    }

    /**
     * 失败回调
     */
    private function failbackProcess() {
        $rnd1 = md5(rand(0, 100));
        $rnd2 = md5(rand(0, 100));
        $challenge = $rnd1 . substr($rnd2, 0, 2);
        $result = [
            'success' => 0,
            'gt' => $this->geetestID,
            'challenge' => $challenge,
            'newCaptcha' =>1
        ];
        $this->response = $result;
    }

    /**
     * 正常模式获取验证结果
     *
     * @param $challenge
     * @param $validate
     * @param $seccode
     * @param $param
     * @param int $jsonFormat
     * @return int
     */
    private function successValidate($challenge, $validate, $seccode,$param, $jsonFormat=1) {
        if (!$this->checkValidate($challenge, $validate)) {
            return 0;
        }
        $query = array(
            "seccode" => $seccode,
            "timestamp" => time(),
            "challenge" => $challenge,
            "captchaid" => $this->geetestID,
            "jsonFormat" => $jsonFormat,
            "sdk" => self::GT_SDK_VERSION
        );
        $query = array_merge($query,$param);
        $url = "http://api.geetest.com/validate.php";
        $client = new $this->guzzleClient;
        $response = $client->post($url, [
            'query' => http_build_query($query)
        ]);
        $response = $response->getBody()->getContents();
        if ($response === false){
            return 0;
        }
        if ($response == md5($seccode)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 宕机模式获取验证结果
     *
     * @param $challenge
     * @param $validate
     * @return int
     */
    private function failValidate($challenge, $validate) {
        if(md5($challenge) == $validate){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 校验
     *
     * @param $challenge
     * @param $validate
     * @return bool
     */
    private function checkValidate($challenge, $validate) {
        if (strlen($validate) != 32) {
            return false;
        }
        if (md5($this->geetestKey . 'geetest' . $challenge) != $validate) {
            return false;
        }
        return true;
    }


    /**
     * 获取验证码配置
     *
     * @param string $userID
     * @param string $clientType
     * @return string
     */
    public function captcha($userID = 'test', $clientType = 'web'){
        $data = array(
            "user_id" => $userID,
            "client_type" => $clientType,
            "ip_address" => '127.0.0.1',
        );
        $status = $this->preProcess($data, 1);
        $response = $this->response;
        $this->redis->set('gt_server',$status);
        $this->redis->set('gt_user_id'.$response['challenge'],$data['user_id'],600);
        return $this->response;
    }


    /**
     * 服务端校验
     *
     * @param $geetestChallenge
     * @param $geetestValidate
     * @param $geetestSeccode
     * @return bool
     */
    public function validate($geetestChallenge, $geetestValidate, $geetestSeccode)
    {
        $gtServer = $this->redis->get('gt_server');
        $userId = $this->redis->get('gt_user_id'.$geetestChallenge);
        if ($gtServer == 1) {
            if ($this->successValidate($geetestChallenge, $geetestValidate, $geetestSeccode,['user_id'=>$userId])) {
                return true;
            }
            return false;
        } else {
            if ($this->failValidate($geetestChallenge, $geetestValidate)) {
                return true;
            }
            return false;
        }
    }


}