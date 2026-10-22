<?php
namespace Google;
class Client {
    private string $state='';
    public function setClientId($v){} public function setClientSecret($v){} public function setRedirectUri($v){} public function addScope($v){} public function setPrompt($v){} public function setAccessToken($v){}
    public function setState($v){$this->state=$v;}
    public function createAuthUrl(){return 'https://provider.example.invalid/oauth?state='.$this->state;}
    public function fetchAccessTokenWithAuthCode($v){file_put_contents(dirname(__DIR__).'/private/provider-count','1',FILE_APPEND);return ['access_token'=>'synthetic-provider-token'];}
}
namespace Google\Service;
class Oauth2 {
    public object $userinfo;
    public function __construct($client){$this->userinfo=new class {public function get(){return (object)json_decode(file_get_contents(dirname(__DIR__).'/private/provider.json'),true,16,JSON_THROW_ON_ERROR);}};}
}
