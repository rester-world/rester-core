<?php if(!defined('__RESTER__')) exit;

require_once dirname(__FILE__) . '/cfg.class.php';
require_once dirname(__FILE__) . '/session.class.php';
require_once dirname(__FILE__) . '/rester_response.class.php';
require_once dirname(__FILE__) . '/rester_verify.class.php';
require_once dirname(__FILE__) . '/rester_config.class.php';
require_once dirname(__FILE__) . '/rester.class.php';

/**
 * Check associative array
 * 연관 배열인지 검사
 * 숫자가 아닌 키값이 하나라도 있으면 연관배열로 추정
 *
 * @param array $arr
 * @return bool
 */
function is_assoc($arr)
{
    $res = false;
    foreach($arr as $k=>$v) if(!is_numeric($k)) $res = true;
    return $res;
}

/**
 * return analyzed parameter
 *
 * @param null|string $key
 * @return bool|mixed
 */
function request_param($key=null)
{
    global $current_rester;
    return $current_rester->request_param($key);
}

/**
 * https://stackoverflow.com/questions/3772096/posting-multidimensional-array-with-php-and-curl
 * @param       $arrays
 * @param array $new
 * @param null  $prefix
 */
function http_build_query_for_curl( $arrays, &$new = array(), $prefix = null ) {

    if ( is_object( $arrays ) ) {
        $arrays = get_object_vars( $arrays );
    }

    foreach ( $arrays AS $key => $value ) {
        $k = isset( $prefix ) ? $prefix . '[' . $key . ']' : $key;
        if ( is_array( $value ) OR is_object( $value )  ) {
            http_build_query_for_curl( $value, $new, $k );
        } else {
            $new[$k] = $value;
        }
    }
}

/**
 * @param string $method
 * @param string $uri
 * @param int $port
 * @param array  $body
 * @param bool   $files
 *
 * @return bool|mixed
 */
function request_uri($method, $uri, $port, $body=[], $files=false)
{
    // 2차배열 이상 처리
    $post_body = null;
    http_build_query_for_curl($body, $post_body);

    if($files)
    {
        $post_body = array_merge($post_body,$files); // 1차원 배열만 전달됨
    }

    $ch = curl_init();

    curl_setopt_array($ch, array(
        CURLOPT_URL => $uri,
        CURLOPT_PORT => $port,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
    ));
    if($method=='POST') curl_setopt($ch,CURLOPT_POSTFIELDS, $post_body);

    $response_body = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //echo curl_error($ch);
    //var_dump(curl_getinfo($ch));
    curl_close($ch);
//    var_dump($response_body);
//    exit;

    if($response_code != 200)
    {
        //$this->error_msg = array_pop(json_decode($this->response_body,true));
        return false;
    }
    return $response_body;
}

/**
 * 외부 서비스 호출
 *
 * @param string $method
 * @param string $name
 * @param string $module
 * @param string $proc
 * @param array  $param
 * @param bool   $files
 *
 * @return bool|array
 */
function exten($method, $name, $module, $proc, $param=[], $files=false)
{
    $result = false;
    $cfg = cfg::request($name);

    try
    {
        if(!($method=='POST' || $method=='GET')) throw new Exception("Allowed \$method [POST|GET].",rester_response::code_request_method);
        if(!$module) throw new Exception("\$module is a required input.",rester_response::code_parameter);
        if(!$proc) throw new Exception("\$proc is a required input.",rester_response::code_parameter);

        if(
            !$cfg ||
            !$cfg[cfg::request_host] ||
            !$cfg[cfg::request_port] ||
            !$cfg[cfg::request_prefix]
        )
            throw new Exception("There is no config.(cfg[request][{$name}])",rester_response::code_config);

        $port = $cfg[cfg::request_port];
        $url = implode('/', [
            $cfg[cfg::request_host],
            $cfg[cfg::request_prefix],
            $module,
            $proc
        ]);

        if($token = request_param('token')) $param['token'] = $token;
        if($secret = request_param('secret')) $param['secret'] = $secret;

        $post_body = [];
        if($method=='GET')
        {
            $query = [];
            foreach($param as $key=>$value)
            {
                $query[] = $key.'='.$value;
            }
            $url .= '?'.urlencode(implode('&',$query));
        } else {
            $post_body = $param;
        }

        $response_body = request_uri($method, $url, $port, $post_body, $files);

//        $ch = curl_init();
//        curl_setopt_array($ch, array(
//            CURLOPT_URL => $url,
//            CURLOPT_PORT => $port,
//            CURLOPT_RETURNTRANSFER => true,
//            CURLOPT_ENCODING => "",
//            CURLOPT_MAXREDIRS => 10,
//            CURLOPT_TIMEOUT => 30,
//            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//            CURLOPT_CUSTOMREQUEST => $method
//        ));
//        if($method=='POST') curl_setopt($ch,CURLOPT_POSTFIELDS, $param);
//
//        $response_body = curl_exec($ch);
//        curl_close($ch);
        $result = json_decode($response_body,true);
    }
    catch (Exception $e)
    {
        rester_response::failed($e->getCode(),$e->getMessage());
        rester_response::error_trace(explode("\n",$e->getTraceAsString()));
    }
    return $result;
}


/**
 * 외부 서비스 호출
 *
 * @param string $name
 * @param string $module
 * @param string $proc
 * @param array  $param
 *
 * @return bool|array
 */
function exten_get($name, $module, $proc, $param=[], $files=false)
{
    return exten('GET',$name,$module,$proc,$param,$files);
}

/**
 * 외부 서비스 호출
 *
 * @param string $name
 * @param string $module
 * @param string $proc
 * @param array  $param
 *
 * @return bool|array
 */
function exten_post($name, $module, $proc, $param=[],$files=false)
{
    return exten('POST',$name,$module,$proc,$param,$files);
}

/**
 * @param array|mixed $res
 *
 * @param bool        $fetch
 *
 * @return array|bool|mixed
 */
function response_data($res, $fetch=true)
{
    $data = false;
    if($res['success'])
    {
        if($fetch && is_array($res['data']) && sizeof($res['data'])==1) $data = $res['data'][0];
        else $data = $res['data'];
    }
    else
    {
        rester_response::failed(rester_response::code_response_fail, implode('/',$res['error']));
    }
    return $data;
}

/**
 * @param string $uri
 *
 * @return string
 */
function cdn_image($uri)
{
    $ext = cdn_get_ext($uri);
    if($ext=='jpg' || $ext=='png' || $ext=='jpeg' || $ext=='svg' || $ext=='gif')
    {
        return cfg::get('file','cdn').'/rester-cdn/image/'.$uri;
    }
    return false;
}

/**
 * @param string $uri
 * @param int $width
 * @param int $height
 *
 * @return string
 */
function cdn_thumb($uri,$width=0,$height=0)
{
    $result = cfg::get('file','cdn').'/rester-cdn/image/'.$uri.'?thumb=true';
    if($width) $result.= '&width='.$width;
    if($height) $result.= '&height='.$height;
    return $result;
}

/**
 * @param string $uri
 *
 * @return string
 */
function cdn_delete($uri)
{
    return cfg::get('file','cdn').'/rester-cdn/delete/'.$uri;
}

/**
 * @param string $uri
 *
 * @return string
 */
function cdn_download($uri)
{
    return cfg::get('file','cdn').'/rester-cdn/download/'.$uri;
}

/**
 * @param string $uri
 *
 * @return bool|string
 */
function cdn_get_ext($uri)
{
    return substr($uri,strpos($uri, '.')+1);
}

/**
 * @param string $uri
 *
 * @return string
 */
function cdn_get_filename($uri)
{
    $ext = cdn_get_ext($uri);
    $sub_url = substr(substr($uri, strpos($uri,'/')+1),0, -(strlen($ext)+1));
    return urldecode(explode('_',base64_decode(urldecode($sub_url)))[1]).'.'.$ext;
}

// -----------------------------------------------------------------------------
/// catch 되지 않은 예외에 대한 처리함수
// -----------------------------------------------------------------------------
set_exception_handler(function($e) {
    rester_response::failed(rester_response::code_system_error, 'System error!!');
    rester_response::error_trace(explode("\n",$e));
    rester_response::run();
});

//-------------------------------------------------------------------------------
/// set php.ini
//-------------------------------------------------------------------------------
set_time_limit(0);
ini_set("session.use_trans_sid", 0); // PHPSESSID 를 자동으로 넘기지 않음
ini_set("url_rewriter.tags","");     // 링크에 PHPSESSID 가 따라다니는것을 무력화
ini_set("default_socket_timeout",500);

ini_set("memory_limit", "1000M");     // 메모리 용량 설정.
ini_set("post_max_size","1000M");
ini_set("upload_max_filesize","1000M");

// -----------------------------------------------------------------------------
/// Set the global variables [_POST / _GET / _COOKIE]
/// initial a post and a get variables.
/// if not support short grobal variables, will be avariable.
// -----------------------------------------------------------------------------
if (isset($HTTP_POST_VARS) && !isset($_POST))
{
    $_POST   = &$HTTP_POST_VARS;
    $_GET    = &$HTTP_GET_VARS;
    $_SERVER = &$HTTP_SERVER_VARS;
    $_COOKIE = &$HTTP_COOKIE_VARS;
    $_ENV    = &$HTTP_ENV_VARS;
    $_FILES  = &$HTTP_POST_FILES;
    if (!isset($_SESSION))
        $_SESSION = &$HTTP_SESSION_VARS;
}

// force to set register globals off
// http://kldp.org/node/90787
if(ini_get('register_globals'))
{
    foreach($_GET as $key => $value) { unset($$key); }
    foreach($_POST as $key => $value) { unset($$key); }
    foreach($_COOKIE as $key => $value) { unset($$key); }
}

function stripslashes_deep($value)
{
    $value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
    return $value;
}

// if get magic quotes gpc is on, set off
// set magic_quotes_gpc off
if (get_magic_quotes_gpc())
{

    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
    $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}

//=============================================================================
/// add slashes
/// 2019.09.24 보안상의 이유로 넣었지만 API들 간의 호출시 자동으로 slashes가 추가되면서
/// 데이터베이스에 slashes가 많이 추가되는 현상이 발생하여 제거함
//=============================================================================
//if(is_array($_POST)) array_walk_recursive($_POST, function(&$item){ $item = addslashes($item); });
//if(is_array($_GET)) array_walk_recursive($_GET, function(&$item){ $item = addslashes($item); });
//if(is_array($_COOKIE)) array_walk_recursive($_COOKIE, function(&$item){ $item = addslashes($item); });
