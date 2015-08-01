<?php
if (!defined('DEBUG')) {
    define('DEBUG', 0);
}

if (DEBUG > 0) {
    // 包含基础的类：初始化相关
    $inc_files = glob(FRAMEWORK_PATH . '*/*.class.php');
    foreach ($inc_files as $inc_file) {
        include $inc_file;
    }
    if (defined('FRAMEWORK_EXTEND_PATH')) {
        //扩展目录用 | 隔开
        $dirs = explode('|', FRAMEWORK_EXTEND_PATH);
        foreach ($dirs as $dir) {
            if ($dir && is_dir($dir)) {
                $inc_files = glob($dir . '*.class.php');
                //load base first
                foreach ($inc_files as $key => $inc_file) {
                    if (preg_match('/\bbase_/i', $inc_file)) {
                        include $inc_file;
                        unset($inc_files[$key]);
                    }
                }
                // load extends
                if ($inc_files) {
                    foreach ($inc_files as $inc_file) {
                        include $inc_file;
                    }
                }
            }
        }
    }
    unset($inc_files, $inc_file, $dirs, $dir);

} else {
    // 语义同上段，优先读取应用定义的目录下的 runtime 文件
    $runtimefile = FRAMEWORK_TMP_PATH . (defined('FRAMEWORK_RUNTIMEFILE') ? FRAMEWORK_RUNTIMEFILE : '_runtime.php');
    if (!(@include($runtimefile))) {
        $content = '';
        if (!is_dir(FRAMEWORK_TMP_PATH)) {
            mkdir(FRAMEWORK_TMP_PATH, 0777, 1);
        }
        // 最低版本需求判断
        PHP_VERSION < '5.0' && exit('Required PHP version 5.0.* or later.');
        // make runtime file
        $inc_files = glob(FRAMEWORK_PATH . '*/*.class.php');
        // 加载除debug目录的文件
        foreach ($inc_files as $inc_file) {
            if (strpos($inc_file, 'debug/') === false) {
                $content .= php_strip_whitespace($inc_file);
            }
        }
        // 加载扩展内核的文件
        if (defined('FRAMEWORK_EXTEND_PATH')) {

            //扩展目录用 | 隔开
            $dirs = explode('|', FRAMEWORK_EXTEND_PATH);
            foreach ($dirs as $dir) {
                if ($dir && is_dir($dir)) {
                    $inc_files = glob($dir . '*.class.php');
                    foreach ($inc_files as $inc_file) {
                        $content .= php_strip_whitespace($inc_file);
                    }
                }
            }
        }
        //写入 runtime 文件
        file_put_contents($runtimefile, $content);
        unset($content, $inc_files, $inc_file, $dirs, $dir);
        //加载 runtime
        include $runtimefile;
    }
}


?>