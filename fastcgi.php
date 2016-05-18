<?php
/**
 * Created by PhpStorm.
 * User: ruansheng
 * Date: 16/5/18
 * Time: 13:56
 */

class FastCGI {

    const FCGI_HOST = '127.0.0.1';
    const FCGI_PORT = 9000;

    const FCGI_RESPONDER = 1;
    const FCGI_BEGIN_REQUEST = 1;
    const FCGI_REQUEST_ID = 1;

    private $sock = null;

    public function __construct() {
        $this->connect();
    }

    /**
     * connect fastcgi server
     */
    public function connect() {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($sock, self::FCGI_HOST, self::FCGI_PORT);
        $this->sock = $sock;
    }

    /**
     * get BeginRequest Body
     * @return string
     */
    public function getBeginRequestBody() {
        return pack("nC6", self::FCGI_RESPONDER, 0, 0, 0, 0, 0, 0);
    }

    /**
     * getPaddingLength
     * @param $body
     * @return int
     */
    public function getPaddingLength($body) {
        $left = strlen($body) % 8;
        if ($left == 0) {
            return 0;
        }
        return (8 - $left);
    }

    /**
     * getHeader
     * @param $type
     * @param $requestId
     * @param $contentLength
     * @param $paddingLength
     * @param int $reserved
     * @return string
     */
    public function getHeader($type, $requestId, $contentLength, $paddingLength, $reserved = 0) {
        return pack("C2n2C2", FCGI_VERSION_1, $type, $requestId, $contentLength, $paddingLength, $reserved);
    }

    /**
     * getPaddingData
     * @param int $paddingLength
     * @return mixed|string
     */
    public function getPaddingData($paddingLength = 0) {
        if ($paddingLength <= 0) {
            return '';
        }
        $paddingArray = array_fill(0, $paddingLength, 0);
        return call_user_func_array("pack", array_merge(array("C{$paddingLength}"), $paddingArray));
    }

    /**
     * _socket_write
     * @param $record
     */
    public function _socket_write($record) {
        socket_write($this->sock, $record);
    }


}

$FastCGI = new FastCGI();

$body = $FastCGI->getBeginRequestBody();
$paddingLength = $FastCGI->getPaddingLength($body);
$header = $FastCGI->getHeader(FastCGI::FCGI_BEGIN_REQUEST, FastCGI::FCGI_REQUEST_ID, strlen($body), $paddingLength, 0);
$record = $header . $body . getPaddingData($paddingLength);
$FastCGI->_socket_write($record);

$env    = array(
    'SCRIPT_FILENAME' => FCGI_SCRIPT_FILENAME,
    'REQUEST_METHOD'  => FCGI_REQUEST_METHOD,
    'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
);