<?php
/**
 * User: djunny
 * Date: 2015-11-27
 * Time: 16:45
 * Mail: 199962760@qq.com
 */

/**
 * generate origin url or rewrite url
 *
 * @param       $control control name like 'index' or 'index-index'
 * @param       $action  action name
 * @param array $params  other params if control has '-', params will bind action value
 */
function url($control, $action = '', $params = array()) {
    if (strpos($control, '-') === false) {
        $control_action = $control . '-' . $action;
    } else {
        $control_action = $control;
        $params = $action;
    }
    // http build query support array
    if ($params) {
        $queries = array();
        if (is_array($params)) {
            foreach ($params as $query => $value) {
                if (is_array($value)) {
                    foreach ($value as $key => $val) {
                        if(is_array($val)){
                            foreach ($val as $son_key => $son_val) {
                                $queries[] = $query.'['.$key.']['.$son_key.']='.rawurldecode($son_val);
                            }
                        }else {
                            $queries[] = $query . '[' . $key . ']=' . rawurlencode($val);
                        }
                    }
                } else {
                    $queries[] = $query . '=' . rawurlencode($value);
                }
            }
            $queries = '&' . implode('&', $queries);
        } else {
            $queries = ($params[0] == '&' ? '' : '&') . $params;
        }
    }
    if (core::$conf['url_rewrite']) {
        $rewrite_info = core::$conf['rewrite_info'];
        $comma = $rewrite_info['comma'] ? $rewrite_info['comma'] : '-';
        $extension = $rewrite_info['ext'];
        return core::rewrite(core::$conf['app_dir'], str_replace('-', $comma, $control_action), $queries, $comma, $extension, 0);
    } else {
        return core::$conf['app_dir'] . '?c=' . $control_action . $queries;
    }
}

?>