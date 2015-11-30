<?php
!defined('FRAMEWORK_PATH') && exit('Access Denied.');

class base_common extends base_control {
    function __construct(&$conf) {
        // extends from base_control
        parent::__construct($conf);
        // bind const variables for template
        VI::assign_value('base_control', 'hi, im from base_control');
    }
}

?>