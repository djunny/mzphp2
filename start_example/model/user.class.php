<?php
!defined('FRAMEWORK_PATH') && exit('Access Deined.');

/**
 * model user
 */
class user extends base_model {
    function __construct() {
        parent::__construct('user', 'id');
    }
}

?>