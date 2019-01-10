<?php


class Curl {
    private $_handles = [];
    private $_mh      = [];
    private $timeout  = null;
    private $info     = [];
    private $headers  = [];
    private $referer  = null;

    public function __construct() {
        $this->_mh = curl_multi_init();
    }

    public function setTimeout($out) {
        $this->timeout = $out;
    }
    
    public function getTimeout(){
        return $this->timeout;
    }

    public function setCookie($cookie){
        $this->cookie = $cookie;
    }

    public function setReferer($ref){
        $this->referer = $ref;
    }

    public function add($url, $cookie=null, $post=null, $proxy=null) {
        if($this->timeout == null){
            die('seta o timeout fdp');
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($cookie != null){
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }

        if ($this->referer != null){
            curl_setopt($ch, CURLOPT_REFERER, $this->referer);
        }

        if ($post != null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
        }

        if ($proxy != null){
            curl_setopt($ch, CURLOPT_PROXY, $proxy);            
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->getTimeout());
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->getTimeout());
        
        curl_multi_add_handle($this->_mh, $ch);
        $this->_handles[] = $ch;
        return $this;
    }
 
    public function run() {
        $running=null;
        do {
            curl_multi_exec($this->_mh, $running);
            usleep (250000);
        } while ($running > 0);
        for($i=0; $i < count($this->_handles); $i++) {
            $out = curl_multi_getcontent($this->_handles[$i]);
            $out = explode("\n\r\n", $out);
            $headers[$i] = $out[0];
            $data[$i]    = $out[1];
            $info[$i] = curl_getinfo($this->_handles[$i]);
            //$data[$i] = json_decode($out);
            curl_multi_remove_handle($this->_mh, $this->_handles[$i]);
        }
        curl_multi_close($this->_mh);
        return ['info'=> $info, 'headers'=>$headers, 'body'=> $data];
    }

    public function isJSON($string){
        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }
}

/*

#example
$keyapi = 'VYZK892qeodPDML7fU6BFAjGtQuh4HWc';
$url_api = "http://falcon.proxyrotator.com:51337/?apiKey={$keyapi}&country=br&port=3128";

$Curl = new Curl();
$Curl->setTimeout(10);
$Curl->add($url_api);
$Curl->add($url_api);
$Curl->add($url_api);
$Curl->add($url_api);
$docsok = $Curl->run();
$docsok = $docsok['body'];


*/
