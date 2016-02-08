<?php

/**
 * Class cli_control
 */
class cli_control extends base_control {

    function __construct(&$conf) {
        if (!core::is_cmd()) {
            exit('Access Denied');
        }
        parent::__construct($conf);
    }
}

?>