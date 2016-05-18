<?php
/**
 * Created by PhpStorm.
 * User: ruansheng
 * Date: 16/5/18
 * Time: 13:39
 */
define('FCGI_HOST', '127.0.0.1');
define('FCGI_PORT', 9000);
define('FCGI_SCRIPT_FILENAME', '/Users/ruansheng/PhpstormProjects/test/property/demo.php');
define('FCGI_REQUEST_METHOD', 'GET');
define('FCGI_REQUEST_ID', 1);

define('FCGI_VERSION_1', 1);
define('FCGI_BEGIN_REQUEST', 1);
define('FCGI_RESPONDER', 1);
define('FCGI_END_REQUEST', 3);
define('FCGI_PARAMS', 4);
define('FCGI_STDIN', 5);
define('FCGI_STDOUT', 6);
define('FCGI_STDERR', 7);

function getBeginRequestBody()
{
    return pack("nC6", FCGI_RESPONDER, 0, 0, 0, 0, 0, 0);
}

function getHeader($type, $requestId, $contentLength, $paddingLength, $reserved=0)
{
    return pack("C2n2C2", FCGI_VERSION_1, $type, $requestId, $contentLength, $paddingLength, $reserved);
}

function getPaddingLength($body)
{
    $left = strlen($body) % 8;
    if ($left == 0)
    {
        return 0;
    }

    return (8 - $left);
}

function getPaddingData($paddingLength=0)
{
    if ($paddingLength <= 0)
    {
        return '';
    }
    $paddingArray = array_fill(0, $paddingLength, 0);
    return call_user_func_array("pack", array_merge(array("C{$paddingLength}"), $paddingArray));
}

function getNameValue($name, $value)
{
    $nameLen  = strlen($name);
    $valueLen = strlen($value);
    $bin      = '';

    // 如果大于127，则需要4个字节来存储，下面的$valueLen也需要如此计算
    if ($nameLen > 0x7f)
    {
        // 将$nameLen变成4个无符号字节
        $b0 = $nameLen << 24;
        $b1 = ($nameLen << 16) >> 8;
        $b2 = ($nameLen << 8) >> 16;
        $b3 = $nameLen >> 24;
        // 将最高位置1，表示采用4个无符号字节表示
        $b3 = $b3 | 0x80;
        $bin = pack("C4", $b3, $b2, $b1, $b0);
    }
    else
    {
        $bin = pack("C", $nameLen);
    }

    if ($valueLen > 0x7f)
    {
        // 将$nameLen变成4个无符号字节
        $b0 = $valueLen << 24;
        $b1 = ($valueLen << 16) >> 8;
        $b2 = ($valueLen << 8) >> 16;
        $b3 = $valueLen >> 24;
        // 将最高位置1，表示采用4个无符号字节表示
        $b3 = $b3 | 0x80;
        $bin .= pack("C4", $b3, $b2, $b1, $b0);
    }
    else
    {
        $bin .= pack("C", $valueLen);
    }

    $bin .= pack("a{$nameLen}a{$valueLen}", $name, $value);

    return $bin;
}

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($sock, FCGI_HOST, FCGI_PORT);

$body   = getBeginRequestBody();
$paddingLength = getPaddingLength($body);
$header = getHeader(FCGI_BEGIN_REQUEST, FCGI_REQUEST_ID, strlen($body), $paddingLength, 0);
$record = $header . $body . getPaddingData($paddingLength);
socket_write($sock, $record);

$env    = array(
    'SCRIPT_FILENAME' => FCGI_SCRIPT_FILENAME,
    'REQUEST_METHOD'  => FCGI_REQUEST_METHOD,
    'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
);

foreach ($env as $key=>$value)
{
    $body          = getNameValue($key, $value);
    $paddingLength = getPaddingLength($body);
    $header        = getHeader(FCGI_PARAMS, FCGI_REQUEST_ID, strlen($body), $paddingLength, 0);
    $record        = $header . $body . getPaddingData($paddingLength);
    socket_write($sock, $record);
}


$body          = "";
$paddingLength = getPaddingLength($body);
$header        = getHeader(FCGI_STDIN, FCGI_REQUEST_ID, 0, $paddingLength, 0);
$record        = $header . $body . getPaddingData($paddingLength);
socket_write($sock, $record);

$body          = "";
$paddingLength = getPaddingLength($body);
$header        = getHeader(FCGI_STDIN, FCGI_REQUEST_ID, 0, $paddingLength, 0);
$record        = $header . $body . getPaddingData($paddingLength);
socket_write($sock, $record);

$header = socket_read($sock, 8);
$header = unpack("Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength/Creserved", $header);
print_r($header);
$len = $header['contentLength'];
$response = '';
while ($len && $buf=socket_read($sock, $len)) {
    $len -= strlen($buf);
    $response .= $buf;
}
//$response = socket_read($sock, $header['contentLength']);
print_r($response);
socket_close($sock);