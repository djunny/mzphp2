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
        //混淆方法名 1=字母混淆 2=乱码混淆 0=不混淆
        'ob_function' => 2,
        //混淆函数产生变量最大长度
        'ob_function_length' => 3,
        //混淆函数调用 1=混淆 0=不混淆 或者 array('eval', 'strpos') 为混淆指定方法
        'ob_call' => 1,
        //混淆函数调用变量产生模式  1=字母混淆 2=乱码混淆 0=不混淆
        'encode_call' => 2,
        //混淆变量 方法参数  1=字母混淆 2=乱码混淆 0=不混淆
        'encode_var' => 2,
        //混淆变量最大长度
        'encode_var_length' => 5,
        //混淆字符串常量  1=字母混淆 2=乱码混淆 0=不混淆
        'encode_str' => 2,
        //混淆字符串常量变量最大长度
        'encode_str_length' => 3,
        // 混淆html 1=混淆 0=不混淆
        'encode_html' => 1,
        // 混淆数字 1=混淆为0x00a 0=不混淆
        'encode_number' => 1,
        // 混淆的字符串 以 gzencode 形式压缩 1=压缩 0=不压缩
        'encode_gz' => 1,
        // 加换行（增加可阅读性）
        'new_line' => 0,
        // 移除注释 1=移除 0=保留
        'remove_comment' => 1,
        // 文件头部增加的注释
        'comment' => '-- mzphp 混淆加密：https://git.oschina.net/mz/mzphp2 ',
        // debug
        'debug' => 1,
        // 重复加密次数，加密次数越多反编译可能性越小，但性能会成倍降低
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