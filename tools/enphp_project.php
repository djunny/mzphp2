<?php
/**
 * EnPHP 混淆加密
 * 本工具用于混淆加密 PHP 项目
 * 使用方法：
 * 1. 将本文件拷至 mzphp 项目中
 * 2. 修改 enphp 中适合你的 option 参数（选）
 * 3. 修改 enphp_control 中 on_index 方法里需要加密的文件(选)
 * 4. 在浏览器中访问本文件，或者使用 php enphp_project.php 命令行模式执行加密
 */
$_GET['c'] = 'enphp-index';

/**
 * enphp encode class
 * Class enphp
 */
class enphp {
    public static $server = 'http://enphp.oschina.mopaasapp.com/api/';
    //'http://localhost/enphp/api/';
    public static $option = array(
        // 混淆方法名
        'ob_function' => 1,
        // 混淆变量长度
        'ob_function_length' => 3,
        // 混淆函数调用
        'ob_call' => 1, // all of call or array('eval', 'strpos')
        // 混淆函数调用变量产生模式
        'encode_call' => 1,
        // 混淆变量 方法参数
        'encode_var' => 1,
        // 混淆变量长度
        'encode_var_length' => 5,
        // 混淆字符串常量
        'encode_str' => 1,
        // 混淆字符串常量变量长度
        'encode_str_length' => 3,
        // 混淆html
        'encode_html' => 1,
        // 加密数字
        'encode_number' => 1,
        // gz 加密字符串项目
        'encode_gz' => 1,
        // 加换行
        'new_line' => 0,
        // 移除所有注释
        'remove_comment' => 1,
        // comment
        'comment' => '-- mzphp 混淆加密：https://git.oschina.net/mz/mzphp2',
        // 重重加密次数，性能下降，慎用，不建议超过2层
        'deep' => 1,
    );

    public static function encode($file) {
        $content = file_get_contents($file);
        $file = strtr($file, array('\\', '/'));
        $file_array = explode('/', $file);
        $file_name = end($file_array);
        $content = rawurlencode(gzencode($content));
        $post = 'file=' . rawurldecode($file_name) . '&data=' . $content;
        foreach (self::$option as $query => $val) {
            $post .= '&option[' . $query . ']=' . rawurlencode($val);
        }
        $header = array(
            'User-Agent' => 'mzphp_encode',
            // proxy for debug
            //'proxy' => array('host' => '127.0.0.1:8888'),
        );
        //
        $retry = 3;
        while ($retry--) {
            $response = spider::POST(self::$server, $post, $header, 300);
            if (!$response || is_numeric($response)) {
                echo $file . " = pack error($response)", "\r\n";
            } else {
                echo $file . " = pack success", "\r\n";
                file_put_contents($file, $response);
                break;
            }
        }
        //
        if (!$retry) {
            echo $file . " = cant encode(request failure)", "\r\n";
            exit;
        }
    }
}

/**
 * control
 * Class enphp_control
 */
class enphp_control {
    public $conf = array();

    function __construct(&$conf) {
        $this->conf = &$conf;
    }

    public function on_index() {
        // 加密模板、runtime.php
        // 发布时可以删除模板、mzphp 框架目录
        $php_files = glob('data/tmp/*.php');
        foreach ($php_files as $file) {
            enphp::encode($file);
        }
    }
}

if (isset($_SERVER['server'])) {
    enphp::$server = $_SERVER['server'];
}

include 'index.php';
?>