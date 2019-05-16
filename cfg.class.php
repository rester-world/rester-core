<?php

/**
 * Class cfg
 */
class cfg
{
    const filename = 'rester.ini';

    const query_version = 'v';
    const query_module = 'm';
    const query_proc = 'proc';

    const version = 'version';
    const module = 'module';
    const proc = 'proc';
    const method = 'method';

    const request_body = 'request-body';
    const request_body_token = 'token';

    const common = 'common';
    const common_timezone   = 'timezone';
    const common_debug_mode = 'debug_mode';
    const common_host       = 'host';

    const cache = 'cache';
    const cache_host    = 'host';
    const cache_port    = 'port';
    const cache_timeout = 'timeout';

    const request = 'request';
    const request_host = 'host';
    const request_port = 'port';
    const request_prefix = 'prefix';

    const session = 'session';
    const session_timeout = 'timeout';

    const access_control = 'access_control';
    const access_control_allows_origin = 'allows_origin';

    const database = 'database';
    const database_default = 'default';
    const database_type = 'type';
    const database_port = 'port';
    const database_user = 'user';
    const database_password = 'password';
    const database_database = 'database';

    // default configuration
    private static $data = [
        self::common=>[
            self::common_debug_mode=>false,
            self::common_timezone=>'Asia/Seoul',
        ],
        self::session=>[
            self::session_timeout=>86400
        ],
        self::access_control=>[
            self::access_control_allows_origin=>'*'
        ],
        self::request_body=>[]
    ];

    /**
     * @return string
     */
    public static function version() { return self::$data[self::version]; }

    /**
     * @return string
     */
    public static function module() { return self::$data[self::module]; }

    /**
     * @return string
     */
    public static function proc() { return self::$data[self::proc]; }

    /**
     * @return string
     */
    public static function method() { return self::$data[self::method]; }

    /**
     * @return array
     */
    public static function request_param() { return self::$data[self::request_body]; }

    /**
     * @return array
     */
    public static function request_body() { return self::$data[self::request_body]; }

    /**
     * @return string
     */
    public static function token() { return self::$data[self::request_body][self::request_body_token]; }

    /**
     * @return string
     */
    public static function timezone() { return self::$data[self::common][self::common_timezone]; }

    /**
     * @return bool
     */
    public static function debug_mode() { return self::$data[self::common][self::common_debug_mode]; }

    /**
     * @return string
     */
    public static function host() { return self::$data[self::common][self::common_host]; }

    /**
     * @return array
     */
    public static function cache() { return self::$data[self::cache]; }

    /**
     * @return string
     */
    public static function cache_host() { return self::$data[self::cache][self::cache_host]; }

    /**
     * @return string
     */
    public static function cache_port() { return self::$data[self::cache][self::cache_port]; }

    /**
     * @return int
     */
    public static function cache_timeout() { return self::$data[self::cache][self::cache_timeout]; }

    /**
     * @param string $select
     *
     * @return array
     */
    public static function request($select) { return self::$data[self::request][$select]; }

    /**
     * @param string $select
     *
     * @return string
     */
    public static function request_host($select) { return self::$data[self::request][$select][self::request_host]; }

    /**
     * @param string $select
     *
     * @return string
     */
    public static function request_port($select) { return self::$data[self::request][$select][self::request_port]; }

    /**
     * @param string $select
     *
     * @return bool
     */
    public static function database($select=self::database_default)
    {
        $result = false;
        if($v = self::$data[self::database][$select]) $result = $v;
        return $result;
    }


    /**
     * @return mixed
     */
    public static function allows_origin() { return self::$data[self::access_control][self::access_control_allows_origin]; }

    /**
     * @return bool
     */
    public static function check_origin()
    {
        $allows = self::allows_origin();
        if($allows=='*') return true;
        if(in_array(self::access_ip(),$allows)) return true;
        return false;
    }

    /**
     * Initialize default config
     *
     * @throws Exception
     */
    public static function init()
    {
        // ---------------------------------------------------------------------
        /// Load config
        // ---------------------------------------------------------------------
        $path = dirname(__FILE__).'/../../cfg/'.self::filename;
        if(!is_file($path))
            throw new Exception("There is no config file.(".self::filename.")", rester_response::code_config);

        $cfg = parse_ini_file($path,true, INI_SCANNER_TYPED);

        // Extract access control
        if($cfg[self::access_control])
        {
            $origin = $cfg[self::access_control][self::access_control_allows_origin];
            if(strpos($origin,',')!==false)
            {
                $cfg[self::access_control][self::access_control_allows_origin] = explode(',', $origin);
            }
        }

        array_walk_recursive($cfg, function(&$v) { $v = trim($v); });
        foreach ($cfg as $section=>$values)
        {
            foreach($values as $kk=>$vv)
            {
                self::$data[$section][$kk] = $vv;
            }
        }

        // ---------------------------------------------------------------------
        /// Version
        // ---------------------------------------------------------------------
        if(preg_match('/^[0-9][0-9.]*$/i',$_GET[self::query_version],$matches))
        {
            self::$data[self::version] = $matches[0];
        }
        else
        {
            if($_GET[self::query_version]=='')
                throw new Exception("Required version name.", rester_response::code_uri);
            else
                throw new Exception("Invalid version name.", rester_response::code_uri);
        }
        unset($_GET[self::query_version]);

        // ---------------------------------------------------------------------
        /// Module name
        // ---------------------------------------------------------------------
        if(preg_match('/^[a-z0-9-_]*$/i',strtolower($_GET[self::query_module]),$matches))
            self::$data[self::module] = $matches[0];
        else
            throw new Exception("Invalid module name.", rester_response::code_uri);
        unset($_GET[self::query_module]);

        // ---------------------------------------------------------------------
        /// Procedure name
        // ---------------------------------------------------------------------
        if(preg_match('/^[a-z0-9-_]*$/i',strtolower($_GET[self::query_proc]),$matches))
            self::$data[self::proc] = $matches[0];
        else
            throw new Exception("Invalid procedure name.", rester_response::code_uri);
        unset($_GET[self::query_proc]);

        // ---------------------------------------------------------------------
        /// Check method
        // ---------------------------------------------------------------------
        if($_SERVER['REQUEST_METHOD']=='POST' || $_SERVER['REQUEST_METHOD']=='GET')
        {
            self::$data[self::method] = strtolower($_SERVER['REQUEST_METHOD']);
        }
        else
        {
            throw new Exception("Invalid request METHOD.(Allowed POST)", rester_response::code_uri);
        }

        // ---------------------------------------------------------------------
        /// Check ip
        // ---------------------------------------------------------------------
        $allows_origin = self::allows_origin();
        if($allows_origin!='*')
        {
            $access_ip = self::access_ip();
            if(!is_array($allows_origin)) $allows_origin = [$allows_origin];
            if(!in_array($access_ip,$allows_origin))
                throw new Exception("Access denied.(Not allowed ip address:{$access_ip})", rester_response::code_access_ip);
        }

        /**
         * extract body parameter from json body, POST and GET
         *
         * json 으로 데이터가 넘어왔을 경우 (php://input)
         * php://input 가 unset이 되지 않아 call_module 함수를 호출할 때에 파라미터 변경이 되지 않는 문게가 있었음
         * get < json < post 순서로 덮어 씌우는 방식으로 해결함
         */
        // Extract request parameter
        // Json, POST, GET
        $json = json_decode(file_get_contents('php://input'),true);
        if(!$json) $json = [];

        if(!is_array($_POST)) $_POST = [];
        if(!is_array($_GET)) $_GET = [];

        self::$data[self::request_body] = $_GET;

        foreach($json as $k=>$v)
        {
            self::$data[self::request_body][$k] = $v;
        }

        foreach($_POST as $k=>$v)
        {
            self::$data[self::request_body][$k] = $v;
        }

        unset($_POST);
        unset($_GET);
    }

    /**
     * @return string
     */
    protected static function access_ip()
    {
        // Check allows ip address
        // Check ip from share internet
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $access_ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        //to check ip is pass from proxy
        else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $access_ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
            $access_ip=$_SERVER['REMOTE_ADDR'];
        }
        return $access_ip;
    }

    /**
     * return config
     *
     * @param string $section
     * @param string $key
     *
     * @return array|string
     */
    public static function get($section='', $key='')
    {
        if($section==='') return self::$data;
        if($section && $key) return self::$data[$section][$key];
        return self::$data[$section];
    }

}

