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
    const FCGI_PARAMS = 4;
    const FCGI_STDIN = 5;
    const FCGI_REQUEST_ID = 1;

    const FCGI_VERSION_1 = 1;

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
        return pack("C2n2C2", self::FCGI_VERSION_1, $type, $requestId, $contentLength, $paddingLength, $reserved);
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

    public function getNameValue($name, $value) {
        $nameLen  = strlen($name);
        $valueLen = strlen($value);
        $bin = '';

        // 如果大于127，则需要4个字节来存储，下面的$valueLen也需要如此计算
        if ($nameLen > 0x7f) {
            // 将$nameLen变成4个无符号字节
            $b0 = $nameLen << 24;
            $b1 = ($nameLen << 16) >> 8;
            $b2 = ($nameLen << 8) >> 16;
            $b3 = $nameLen >> 24;
            // 将最高位置1，表示采用4个无符号字节表示
            $b3 = $b3 | 0x80;
            $bin = pack("C4", $b3, $b2, $b1, $b0);
        } else {
            $bin = pack("C", $nameLen);
        }

        if ($valueLen > 0x7f) {
            // 将$nameLen变成4个无符号字节
            $b0 = $valueLen << 24;
            $b1 = ($valueLen << 16) >> 8;
            $b2 = ($valueLen << 8) >> 16;
            $b3 = $valueLen >> 24;
            // 将最高位置1，表示采用4个无符号字节表示
            $b3 = $b3 | 0x80;
            $bin .= pack("C4", $b3, $b2, $b1, $b0);
        } else {
            $bin .= pack("C", $valueLen);
        }
        $bin .= pack("a{$nameLen}a{$valueLen}", $name, $value);
        return $bin;
    }

    /**
     * _socket_write
     * @param $record
     * @return bool
     */
    public function _socket_write($record) {
        return socket_write($this->sock, $record);
    }

    /**
     * _socket_read
     * @param $size
     * @return string
     */
    public function _socket_read($size) {
        $header = socket_read($this->sock, $size);
        return $header;
    }

    /**
     * _socket_close
     */
    public function _socket_close() {
        socket_close($this->sock);
    }

}

$FastCGI = new FastCGI();

$body = $FastCGI->getBeginRequestBody();
$paddingLength = $FastCGI->getPaddingLength($body);
$header = $FastCGI->getHeader(FastCGI::FCGI_BEGIN_REQUEST, FastCGI::FCGI_REQUEST_ID, strlen($body), $paddingLength, 0);
$record = $header . $body . $FastCGI->getPaddingData($paddingLength);
$ret = $FastCGI->_socket_write($record);

$env = array(
    'SCRIPT_FILENAME' => '/Users/ruansheng/phpstormProjects/test/array/1.php',
    'REQUEST_METHOD'  => 'GET',
    'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
);

foreach ($env as $key=>$value) {
    $body          = $FastCGI->getNameValue($key, $value);
    $paddingLength = $FastCGI->getPaddingLength($body);
    $header        = $FastCGI->getHeader(FastCGI::FCGI_PARAMS, FastCGI::FCGI_REQUEST_ID, strlen($body), $paddingLength, 0);
    $record        = $header . $body . $FastCGI->getPaddingData($paddingLength);
    $ret = $FastCGI->_socket_write($record);
}

$body          = "";
$paddingLength = $FastCGI->getPaddingLength($body);
$header        = $FastCGI->getHeader(FastCGI::FCGI_STDIN, FastCGI::FCGI_REQUEST_ID, 0, $paddingLength, 0);
$record        = $header . $body . $FastCGI->getPaddingData($paddingLength);
$ret = $FastCGI->_socket_write($record);

$body          = "";
$paddingLength = $FastCGI->getPaddingLength($body);
$header        = $FastCGI->getHeader(FastCGI::FCGI_STDIN, FastCGI::FCGI_REQUEST_ID, 0, $paddingLength, 0);
$record        = $header . $body . $FastCGI->getPaddingData($paddingLength);
$ret = $FastCGI->_socket_write($record);

$header = $FastCGI->_socket_read(8);

$header = unpack("Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength/Creserved", $header);

print_r($header);

$len = $header['contentLength'];
$response = '';
while ($len && $buf = $FastCGI->_socket_read($len)) {
    $len -= strlen($buf);
    $response .= $buf;
}
print_r($response);

$FastCGI->_socket_close();