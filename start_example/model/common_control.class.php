<?php

/*
因为 common_control 需要依赖 base_common 
所以，mzphp2 框架在运行前会先载入 base_ 开头的文件。
然后再载入其它扩展类或文件。
*/

class common_control extends base_common {
    function __construct(&$conf) {
        // extends from base_common
        parent::__construct($conf);
        // bind variables for  template
        $var = 'hi, im from common_control';
        VI::assign('common_control', $var);
    }
}

?>