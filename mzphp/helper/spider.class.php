<?php

class spider {
    /**
     * @var int record last response code
     */
    public static $last_response_code = -1;

    /**
     * @var string last redirect url
     */
    public static $url = '';

    /**
     * remove all html tag
     *
     * @param $html
     * @return mixed
     */
    public static function no_html($html) {
        return self::reg_replace($html, array('<(*)>' => ''));
    }

    /**
     * convert html to text
     *
     * @param $html
     * @return mixed|string
     */
    public static function html2txt($html) {
        // html_entity_decode 中 &nbsp; 会导致乱码
        $html = strtr($html, array(
            '&nbsp;' => ' ',
            '&rdquo;' => '”',
            '&ldquo;' => '“',
            //"\xA0" => ' ',
        ));
        $html = preg_replace('/^[\s\t]+/is', ' ', $html);
        $html = preg_replace('#<?xml[\s\S]*?>#is', '', $html);
        $html = preg_replace('#<!--[\s\S]*?-->#is', '', $html);
        $html = preg_replace('#<!doc[\s\S]*?>#is', '', $html);
        $html = preg_replace('#<(head|script|iframe|frame|noscript|noframes|option|style)[\s\S]*?</\1>#is', '', $html);
        $html = preg_replace('#<(br|hr|li|ol|ul|dl|h\d|dd|dt|center|form|table|tr|marquee|div|pre|p|blockquote).*?>#is', "\n", $html);
        // 用 strip_tag 会乱码，纠结...
        $html = self::strip_tags($html);
        // decode entities
        $html = html_entity_decode($html, ENT_COMPAT, 'UTF-8');
        $html = preg_replace('#([\r\n]\s+[\r\n])+#is', "\n", $html);

        $html = str_replace(array("\r", "\n\n"), "\n", $html);
        while (strpos($html, "\n\n") !== false) {
            $html = str_replace("\n\n", "\n", $html);
        }
        return $html;
    }

    /**
     * alias for strip_tag / fix strip_tag unicode bug
     *
     * @param        $text
     * @param string $tags
     * @return mixed
     */
    public static function strip_tags($text, $tags = '') {
        preg_match_all('/<([\w\-\.]+)[\s]*\/?[\s]*>/si', strtolower(trim($tags)), $tags);
        $tags = array_unique($tags[1]);
        $searches = array();
        static $block_set = array(
            'head' => 1,
            'script' => 1,
            'iframe' => 1,
            'frame' => 1,
            'noscript' => 1,
            'noframes' => 1,
            'option' => 1,
            'style' => 1,
        );
        //注释
        $searches[] = '#<!--[\s\S]*?-->#is';
        //ie 判断
        $searches[] = '#<\!--[if[^\]]*?\]>[\S\s]<\!\[endif\]-->#is';
        if (is_array($tags) && count($tags) > 0) {
            $line_tags = $block_tags = '';
            foreach ($tags as $tag) {
                if (!$tag) {
                    continue;
                }
                if (isset($block_set[$tag])) {
                    unset($block_set[$tag]);
                }
                $line_tags .= $tag . '|';
            }
            $block_set = array_keys($block_set);
            $block_tags = implode('|', $block_set);
            if ($block_tags) {
                $searches[] = '#<(' . $block_tags . ')\b[\s\S]*?</\1>#is';
            }
            if ($line_tags) {
                $line_tags = substr($line_tags, 0, -1);
                $searches[] = '#<(?!(?:' . $line_tags . ')|\/(?:' . $line_tags . ')\b)[^>]*?>#si';
            }
            return preg_replace($searches, '', $text);
        } else {
            $searches[] = '#<(' . implode('|', $block_set) . ')\b[\s\S]*?</\1>#is';
            $searches[] = '#<\/?[^>]*?>#si';
            return preg_replace($searches, '', $text);
        }
    }

    /**
     * cut string from $start to $end
     *
     * @param        $html
     * @param string $start
     * @param string $end
     * @return string
     */
    public static function cut_str($html, $start = '', $end = '') {
        if ($start) {
            $html = stristr($html, $start, false);
            $html = substr($html, strlen($start));
        }
        $end && $html = stristr($html, $end, true);
        return $html;
    }
    //
    /*
    */
    /**
     * mask match string:
     *
     * spider::mask_match('123abc123', '123(*)123') = abc
     * spider::mask_match('abc123', '(*)123') = abc
     * spider::mask_match('123abcabc', '(*)abc') = 123
     * spider::mask_match('123abcdef', '(*)abc', true) = 123abc
     *
     * @param            $html
     * @param            $pattern
     * @param bool|false $returnfull
     * @return string
     */
    public static function mask_match($html, $pattern, $returnfull = false) {
        $part = explode('(*)', $pattern);
        if (count($part) == 1) {
            return '';
        } else {
            if ($part[0] && $part[1]) {
                $res = self::cut_str($html, $part[0], $part[1]);
                if ($res) {
                    return $returnfull ? $part[0] . $res . $part[1] : $res;
                }
            } else {
                //pattern=xxx(*)
                if ($part[0]) {
                    if (strpos($html, $part[0]) !== false) {
                        $html = explode($part[0], $html);
                        if ($html[1]) {
                            return $returnfull ? $part[0] . $html[1] : $html[1];
                        }
                    }
                } elseif ($part[1]) {
                    //pattern=(*)xxx
                    if (strpos($html, $part[1]) !== false) {
                        $html = explode($part[1], $html);
                        if ($html[0]) {
                            return $returnfull ? $html[0] . $part[1] : $html[0];
                        }
                    }
                }
            }
            return '';
        }
    }

    //
    /*
        //replace single mode
    */
    /**
     * replace by array replace_from  => replace_to (support reg & str & mask)
     *
     *  example :
     * spider::reg_replace('abcdefg', 'e(*)') = abcd
     * spider::reg_replace('abcdefg', array('#e.+$#is'=> 'hij')) = abcdhij
     * spider::reg_replace('abcd123', array('#\d+#s'=> '')) = abcd
     * spider::reg_replace('abcd123', array('cd'=> 'dc')) = abdc123
     * //replace multi pattern
     * spider::reg_replace('abcd123', array(
     * 'cd'=> 'dc',
     * '1(*)'=> '321',
     * '#\d+#s'=> '111',
     * )) = abdc111
     *
     * @param $html
     * @param $patterns
     * @return mixed
     */
    public static function reg_replace($html, $patterns) {
        if (!is_array($patterns)) {
            $patterns = array($patterns => '');
        }
        foreach ($patterns as $search => $replace) {
            // mask mastch replace
            if (strpos($search, '(*)') !== false) {
                while ($searchhtml = self::mask_match($html, $search, true)) {
                    if ($searchhtml) {
                        $html = str_replace($searchhtml, $replace, $html);
                        continue;
                    }
                    break;
                }
            } elseif (preg_match('/^([\#\/\|\!\@]).+\\1([ismSMI]+)?$/is', $search)) {
                //regexp replace
                $html = preg_replace($search, $replace, $html);
            } else {
                //str replace
                $html = str_replace($search, $replace, $html);
            }
        }
        return $html;
    }


    //match
    /*
    */
    /**
     * match string from pattern
     *
     *
     * $url = 'http://www.sogou.com/web?query='.urlencode($key).'&ie=utf8';
     * $html = spider::fetch_url($url, '', array('Referer'=>'http://www.sogou.com/'));
     *
     * #useage 1
     * // get title by regexp
     * $list = spider::match($html, array('listblock' => array('title' => '/<title>(.*?)<\/title>/is',)));
     * // get title by mask match
     * $list = spider::match($html, array('listblock' => array('title2' => '<title>(*)</title>',)));
     *
     *
     * #useage 2
     *
     * $keywordlist = spider::match($html, array('list'=>array(
     * 'cut' => '相关搜索</caption>(*)</tr></table>',
     * 'pattern' => '#id="sogou_\d+_\d+">(?<key>[^>]*?)</a>#is',
     * )));
     * $newarr = array();
     * foreach($keywordlist['list'] as $key=>$val){
     * $newarr[$val['key']] = array('key'=>$val['key']);
     * }
     *
     * @param       $html
     * @param       $patterns
     * @param array $option
     * @return array
     * @throws Exception
     */
    public static function match($html, $patterns, $option = array('url' => '')) {
        $tmplist = array();
        //sleep
        if (isset($patterns['_sleep'])) {
            usleep($patterns['_sleep']);
            unset($patterns['_sleep']);
        }
        //pre process =replace
        if (isset($patterns['_replace'])) {
            if (!is_array($patterns['_replace'])) {
                $patterns['_replace'] = array($patterns['_replace'] => '');
            }
            $html = self::reg_replace($html, $patterns['_replace']);
            unset($patterns['_replace']);
        }
        $extractor = NULL;
        $dom = NULL;
        //next fetch
        $fetchqueue = array();
        foreach ($patterns as $key => $val) {
            $value = NULL;
            if (!is_array($val)) {
                $val = array($val);
            }
            if (isset($val['pattern'])) {
                //pre process
                $matchhtml = self::match_pre_process($html, $val);
                //support multi pattern
                if (!is_array($val['pattern'])) {
                    $val['pattern'] = array($val['pattern']);
                }
                //regexp match it
                foreach ($val['pattern'] as $pattern) {
                    if (strpos($pattern, '(*)') === false) {
                        $value = self::reg_match($matchhtml, $pattern);
                        if ($value) {
                            break;
                        }
                    } else {
                        // match field by mask_match

                        $value = self::mask_match($matchhtml, $pattern);

                        if ($value) {
                            self::match_process($value, $val['process']);
                            break;
                        }
                    }
                }
            } elseif (isset($val['selector'])) {


            } else {
                //multi mask match pattern
                foreach ($val as &$pattern_array) {
                    if (!is_array($pattern_array) || !isset($pattern_array['pattern'])) {
                        $pattern_array = array(
                            array('pattern' => array($pattern_array))
                        );
                    }
                    $find_value = false;
                    foreach ($pattern_array as $pattern_info) {
                        if (!isset($pattern_info['pattern'])) {
                            continue;
                        }
                        //pre process
                        $matchhtml = self::match_pre_process($html, $val);
                        //not html to match then match next pattern
                        if (!$matchhtml) {
                            continue;
                        }

                        foreach ($pattern_info['pattern'] as $pattern) {
                            /*
                            if($pattern == 'extract'){
                                // get extract
                                if($extractor == NULL){
                                    $extractor = new textExtract($html);
                                }
                                $value = $extractor->getContent();
                                $value = $value['content'];
                                break;
                            }elseif($pattern == 'extract_title'){
                                // get title
                                if($extractor == NULL){
                                    $extractor = new textExtract($html);
                                }
                                $value = $extractor->getTitle();
                                break;
                            }else{
                            */
                            // string match
                            $value = self::str_match($html, $pattern, $dom, $option);
                            //}
                            if ($value) {
                                $find_value = true;
                                // when find processor
                                self::match_process($value, $pattern_info['process']);
                                break;
                            }
                            //or match next pattern
                        }
                    }
                    if ($find_value) {
                        break;
                    }
                }
            }
            $tmplist[$key] = $value;
        }

        //unset dom
        if ($dom) {
            //@$dom->unloadDocument();
        }
        //next fetch
        if ($fetchqueue) {
            foreach ($fetchqueue as $url) {
                $html = self::fetch_url($url['url']);
                $matches = self::match($html, $url['patterns']);
                $tmplist[$url['key']][$url['index']]['fetched'] = $matches;
            }
        }
        return $tmplist;
    }

    /**
     * after match value process
     *
     * @param $value
     * @param $process
     */
    private static function match_process(&$value, &$process) {
        if ($process) {
            if (!is_array($process)) {
                $process = array($process);
            }
            foreach ($process as $index => $processor) {
                $value = call_user_func($processor, $value);
            }
        }
    }

    /**
     * before match value process
     *
     * @param $html
     * @param $pattern_info
     * @return mixed|string
     */
    private static function match_pre_process($html, &$pattern_info) {
        $matchhtml = $html;
        // cut it short and run faster
        if (isset($pattern_info['cut'])) {
            // support multi patterns
            if (!is_array($pattern_info['cut'])) {
                $pattern_info['cut'] = array($pattern_info['cut']);
            }
            // until find match html
            foreach ($pattern_info['cut'] as $pattern) {
                $matchhtml = self::mask_match($html, $pattern);
                if ($matchhtml) {
                    break;
                }
            }
        }
        //replace html
        if (isset($pattern_info['_replace'])) {
            if (!is_array($pattern_info['_replace'])) {
                $pattern_info['_replace'] = array($pattern_info['_replace'] => '');
            }
            $matchhtml = self::reg_replace($matchhtml, $pattern_info['_replace']);
        }
        return $matchhtml;
    }


    /**
     * string match
     *
     * spider::str_match('123', '1(*)3') = 2
     * spider::str_match('123', '1(\d+)3') = 2
     *
     * @param $str
     * @param $pattern
     * @param $dom
     * @param $option
     * @return mixed|string
     */
    public static function str_match($str, $pattern, &$dom, $option) {
        $value = '';
        //array mask pattern
        if (strpos($pattern, '(*)') !== false) {
            $value = self::mask_match($str, $pattern);
        } elseif (substr($pattern, 0, 4) == 'DOM:') {
            return self::dom_match($str, $pattern, $dom, $option);
        } elseif (strpos($pattern, '(') !== false) {
            //has reg match field
            preg_match_all($pattern, $str, $value);
            //return first match group
            $value = $value[1][0];
        }
        return $value;
    }

    /**
     * match by dom (deprecated)
     *
     * @param $html
     * @param $pattern
     * @param $dom
     * @param $option
     * @return mixed
     */
    public static function dom_match($html, $pattern, &$dom, $option) {
        if (function_exists('dom_match')) {
            return call_user_func('dom_match', $html, $pattern, $dom, $option);
        }
        return '';
    }


    /**
     * match by regexp
     *
     * @param     $html
     * @param     $reg
     * @param int $return_index
     * @return array
     */
    public static function reg_match($html, $reg, $return_index = -1) {
        $list = array();
        preg_match_all($reg, $html, $list);
        self::filter_list($list);
        if ($return_index == -1) {
            return $list;
        } else {

            return $list[$return_index];
        }
    }

    /**
     * filter number index in list
     *
     * @param $list
     */
    private static function filter_list(&$list) {
        foreach ($list as $key => $val) {
            if (is_numeric($key)) {
                unset($list[$key]);
            }
        }
        $keys = array_keys($list);
        foreach ($keys as $idx => $key) {
            if (is_numeric($key)) continue;
            foreach ($list[$key] as $index => $value) {
                $list[$index][$key] = $value;
            }
            unset($list[$key]);
        }
    }

    /**
     * relative path to absolute
     *
     * @param $base_url
     * @param $src_url
     * @return string
     */
    public static function abs_url($base_url, $src_url) {
        if (!$src_url) {
            return '';
        }
        $src_info = parse_url($src_url);
        if (isset($src_info['scheme'])) {
            return $src_url;
        }
        $base_info = parse_url($base_url);
        $url = $base_info['scheme'] . '://' . $base_info['host'];
        if (!isset($src_info['path'])) {
            $src_info['path'] = '';
        }
        if (substr($src_info['path'], 0, 1) == '/') {
            $path = $src_info['path'];
        } else {
            //fixed only ?
            if (empty($src_info['path'])) {
                $path = ($base_info['path']);
            } else {
                // fix dirname
                if (substr($base_info['path'], -1) == '/') {
                    $path = $base_info['path'] . $src_info['path'];
                } else {
                    $path = (dirname($base_info['path']) . '/') . $src_info['path'];
                }
            }
        }
        $rst = array();
        $path_array = explode('/', $path);
        if (!$path_array[0]) {
            $rst[] = '';
        }
        foreach ($path_array as $key => $dir) {
            if ($dir == '..') {
                if (end($rst) == '..') {
                    $rst[] = '..';
                } elseif (!array_pop($rst)) {
                    $rst[] = '..';
                }
            } elseif ($dir && $dir != '.') {
                $rst[] = $dir;
            }
        }
        if (!end($path_array)) {
            $rst[] = '';
        }
        $url .= implode('/', $rst);
        $url = str_replace('\\', '/', $url);
        $url = str_ireplace('&amp;', '&', $url);
        return $url . ($src_info['query'] ? '?' . $src_info['query'] : '');
    }

    /**
     * HTTP GET
     *
     * @param       $url
     * @param array $headers
     * @param int   $timeout
     * @param int   $deep
     * @return bool|string
     * @throws Exception
     */
    public static function GET($url, $headers = array(), $timeout = 5, $deep = 0) {
        return self::fetch_url($url, '', $headers, $timeout, $deep);
    }

    /**
     * HTTP POST
     *
     * @param       $url
     * @param       $post
     * @param array $headers
     * @param int   $timeout
     * @param int   $deep
     * @return bool|string
     * @throws Exception
     */
    public static function POST($url, $post, $headers = array(), $timeout = 5, $deep = 0) {
        return self::fetch_url($url, $post, $headers, $timeout, $deep);
    }

    /**
     * fetch url
     *
     * @param        $url
     * @param string $post
     * @param array  $headers
     * @param int    $timeout
     * @param int    $deep
     * @return bool|string
     * @throws Exception
     */
    public static function fetch_url($url, $post = '', $headers = array(), $timeout = 5, $deep = 0) {
        if ($deep > 5) throw new Exception('超出 fetch_url() 最大递归深度！');
        static $stream_wraps = null;
        if ($stream_wraps == null) {
            $stream_wraps = stream_get_wrappers();
        }
        static $allow_url_fopen = null;
        if ($allow_url_fopen == null) {
            $allow_url_fopen = strtolower(ini_get('allow_url_fopen'));
            $allow_url_fopen = (empty($allow_url_fopen) || $allow_url_fopen == 'off') ? 0 : 1;
        }
        !is_array($headers) && $headers = array();
        //headers
        $HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
        empty($HTTP_USER_AGENT) && $HTTP_USER_AGENT = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)';

        $matches = parse_url($url);
        $host = $matches['host'];
        $path = isset($matches['path']) ? $matches['path'] . (!empty($matches['query']) ? '?' . $matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? $matches['port'] : 80;
        $https = $matches['scheme'] == 'https' ? true : false;
        $charset = '';
        $defheaders = array(
            'Accept' => '*/*',
            'User-Agent' => $HTTP_USER_AGENT,
            'Accept-Encoding' => 'gzip, deflate',
            'Host' => $host,
            'Connection' => 'Close',
            'Accept-Language' => 'zh-cn',
        );


        if (!empty($post)) {
            $defheaders['Cache-Control'] = 'no-cache';
            $out = "POST {$path} HTTP/1.0\r\n";
        } else {
            $out = "GET {$path} HTTP/1.0\r\n";
        }

        $socketmode = !$https && function_exists('fsockopen') && function_exists('mime_content_type') ? true : false;
        // curl or socket
        $fetchmode = function_exists('curl_init') || isset($headers['curl']) ? 'curl' : ($socketmode ? 'socket' : '');
        //set support
        if ($headers['charset']) {
            $charset = $headers['charset'];
        }
        unset($headers['curl'], $headers['charset']);
        // merge headers
        if (is_array($headers) && $headers) {
            foreach ($headers as $key => $val) {
                $defheaders[$key] = $val;
            }
        }

        if ($fetchmode == 'socket') {
            $limit = 1024000000;
            $ip = '';
            $return = '';
            $defheaders['Content-Type'] = 'application/x-www-form-urlencode';
            // build post
            if (is_array($post)) {
                $boundary = '';
                $post_body = '';
                foreach ($post as $k => $v) {
                    if ($v[0] == '@') {
                        $v = substr($v, 1);
                        if ($v && is_file($v)) {
                            if (!$boundary) {
                                $boundary = '---------------upload' . uniqid('spider');
                            }
                            $mime = mime_content_type($v);
                            $post_body .= "\r\n" . 'Content-Disposition: form-data; name="' . $k . '"; filename="' . $v . '"' . "\r\n"
                                . 'Content-Type: ' . $mime . "\r\n\r\n" . file_get_contents($v) . "\r\n--" . $boundary;
                            unset($post[$k]);
                        }
                    }
                }
                if ($boundary) {
                    if ($post) {
                        foreach ($post as $k => $v) {
                            $post_body .= "\r\n" . 'Content-Disposition: form-data; name="' . $k . '"' . "\r\n\r\n" . $v . "\r\n--" . $boundary;
                        }
                    }
                    $post_body = '--' . $boundary . $post_body . '--';
                    $post = $post_body;
                    $defheaders['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
                } else {
                    $post = http_build_query($post);
                }
                $defheaders['Content-Length'] = strlen($post);
            }

            foreach ($defheaders as $hkey => $hval) {
                $out .= $hkey . ': ' . $hval . "\r\n";
            }
            $out .= "\r\n";
            //append post body
            if (!empty($post)) {
                $out .= $post;
            }
            $host == 'localhost' && $ip = '127.0.0.1';
            $fp = @fsockopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout);
            if (!$fp) {
                return FALSE;
            } else {
                stream_set_blocking($fp, TRUE);
                stream_set_timeout($fp, $timeout);
                @fwrite($fp, $out);
                $status = stream_get_meta_data($fp);
                $gzip = false;
                if (!$status['timed_out']) {
                    $starttime = time();
                    while (!feof($fp)) {
                        if (($header = @fgets($fp)) && ($header == "\r\n" || $header == "\n")) {
                            break;
                        } else {
                            $header = strtolower($header);
                            if (substr($header, 0, 9) == 'location:') {
                                $location = trim(substr($header, 9));
                                self::$url = $location;
                                return self::fetch_url($location, $timeout, $post, $headers, $deep + 1);
                            } else if (strpos($header, 'content-encoding:') !== false
                                && strpos($header, 'gzip') !== false
                            ) {
                                //is gzip
                                $gzip = true;
                            } else if (strpos($header, 'content-type:') !== false) {
                                preg_match('@Content-Type:\s+([\w/+]+)(;\s+charset=([\w-]+))?@i', $header, $charsetmatch);
                                if (isset($charsetmatch[3])) {
                                    $charset = $charsetmatch[3];
                                }
                            }
                        }
                    }
                    $stop = false;
                    while (!feof($fp) && !$stop) {
                        $data = fread($fp, ($limit == 0 || $limit > 8192 ? 8192 : $limit));
                        $return .= $data;
                        if ($limit) {
                            $limit -= strlen($data);
                            $stop = $limit <= 0;
                        }
                        if (time() - $starttime > $timeout) break;
                    }
                    if ($gzip) {
                        $return = self::gzdecode($return);
                    }
                }
                @fclose($fp);
                return self::convert_html_charset($return, $charset);
            }
        } elseif ($fetchmode == 'curl') {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
            if ($https) {
                // 证书
                if (isset($defheaders[CURLOPT_SSLCERT])) {
                    $ssl_verifypeer = 1;
                    //使用证书：cert 与 key 分别属于两个.pem文件
                    curl_setopt($ch, CURLOPT_SSLCERT, realpath($defheaders[CURLOPT_SSLCERT]));
                    curl_setopt($ch, CURLOPT_SSLKEY, realpath($defheaders[CURLOPT_SSLKEY]));
                    if (isset($defheaders[CURLOPT_SSLCERTTYPE])) {
                        curl_setopt($ch, CURLOPT_SSLCERTTYPE, $defheaders[CURLOPT_SSLCERTTYPE]);
                    }
                    if (isset($defheaders[CURLOPT_SSLKEYTYPE])) {
                        curl_setopt($ch, CURLOPT_SSLKEYTYPE, $defheaders[CURLOPT_SSLKEYTYPE]);
                    }
                    // unset ssl index
                    unset($defheaders[CURLOPT_SSLCERTTYPE], $defheaders[CURLOPT_SSLCERT], $defheaders[CURLOPT_SSLKEYTYPE], $defheaders[CURLOPT_SSLKEY]);
                } else if (isset($defheaders[CURLOPT_SSLCERT])) {
                    $ssl_verifypeer = 1;
                    // 单证书模式
                    // 严格检查证书
                    curl_setopt($ch, CURLOPT_SSLCERT, realpath($defheaders[CURLOPT_SSLCERT]));
                    // unset ssl index
                    unset($defheaders[CURLOPT_SSLCERT]);
                } else {
                    $ssl_verifypeer = 0;
                }
                // support cainfo
                if (isset($defheaders[CURLOPT_CAINFO])) {
                    curl_setopt($ch, CURLOPT_CAINFO, realpath($defheaders[CURLOPT_CAINFO]));
                    unset($defheaders[CURLOPT_CAINFO]);
                }
                // support capath
                if (isset($defheaders[CURLOPT_CAPATH])) {
                    curl_setopt($ch, CURLOPT_CAPATH, realpath($defheaders[CURLOPT_CAPATH]));
                    unset($defheaders[CURLOPT_CAPATH]);
                }
                // 严格检查证书
                if (isset($defheaders[CURLOPT_SSL_VERIFYPEER])) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $defheaders[CURLOPT_SSL_VERIFYPEER]);
                    unset($defheaders[CURLOPT_SSL_VERIFYPEER]);
                } else {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl_verifypeer);
                }
                // 从证书中检查SSL加密算法是否存在
                if (isset($defheaders[CURLOPT_SSL_VERIFYHOST])) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $defheaders[CURLOPT_SSL_VERIFYHOST]);
                    unset($defheaders[CURLOPT_SSL_VERIFYHOST]);
                } else {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                }
            }
            // fix IN PHP 5.6
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            }
            // post
            if ($post) {
                curl_setopt($ch, CURLOPT_POST, 1);
                //find out post file use multipart/form-data
                $is_multi_part = 0;
                if (is_array($post)) {
                    $is_curl_file = version_compare(phpversion(), '5.5.0') >= 0 && class_exists('CURLFile') ? true : false;
                    foreach ($post as $index => $value) {
                        if ($value[0] == '@') {
                            if ($is_curl_file) {
                                $post[$index] = new CURLFile(realpath(substr($value, 1)));
                            }
                            $is_multi_part = 2;
                        }
                    }
                } else {
                    //is string
                    $is_multi_part = 1;
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $is_multi_part ? $post : http_build_query($post));
            }

            //多ip下，设置出口ip
            if (isset($defheaders['ip'])) {
                curl_setopt($ch, CURLOPT_INTERFACE, $defheaders['ip']);
                unset($defheaders['ip']);
            }
            //gzip compress
            if (isset($defheaders['Accept-Encoding'])) {
                curl_setopt($ch, CURLOPT_ENCODING, $defheaders['Accept-Encoding']);
                unset($defheaders['Accept-Encoding']);
            }


            //使用代理
            /*
                'proxy' =>array(
                    'type' => '', //HTTP or SOCKET
                    'host' => 'ip:port',
                    'auth' => 'BASIC:user:pass',
                );
            */
            if ($defheaders['proxy']) {
                $proxy_type = strtoupper($defheaders['proxy']['type']) == 'SOCKET' ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP;
                curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy_type);
                curl_setopt($ch, CURLOPT_PROXY, $defheaders['proxy']['host']);
                //代理要认证
                if ($headers['proxy']['auth']) {
                    list($auth_type, $auth_user, $auth_pass) = explode(':', $headers['proxy']['auth']);
                    $auth_type = $auth_type == 'NTLM' ? CURLAUTH_BASIC : CURLAUTH_NTLM;
                    curl_setopt($ch, CURLOPT_PROXYAUTH, $auth_type);
                    $user = "" . $auth_user . ":" . $auth_pass . "";
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user);
                }
            }
            unset($defheaders['proxy']);

            // set version 1.0
            //curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $deep ? $deep : 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            //build curl headers
            $header_array = array();
            foreach ($defheaders as $key => $val) {
                $header_array[] = $key . ': ' . $val;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);

            $data = curl_exec($ch);

            if (curl_errno($ch)) {
                //throw new Exception('Errno'.curl_error($ch));//捕抓异常
            }
            if (!$data) {
                curl_close($ch);
                return '';
            }
            //for debug request header
            //print_r($defheaders);$info = curl_getinfo($ch, CURLINFO_HEADER_OUT );print_r($info);echo is_array($post) ? http_build_query($post) : $post;exit;
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            self::$last_response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            self::$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $header = substr($data, 0, $header_size);
            $data = substr($data, $header_size);
            //extract last response header
            $header = explode("\r\n\r\n", trim($header));
            $header = array_pop($header);
            //match charset
            if (!$charset) {
                preg_match('@Content-Type:\s*([\w\/]+)(;\s+charset\s*=\s*([\w-]+))?@is', $header, $charsetmatch);
                if (isset($charsetmatch[3])) {
                    $charset = $charsetmatch[3];
                }
            }
            return self::convert_html_charset($data, $charset);
        } elseif ($https && $allow_url_fopen && in_array('https', $stream_wraps)) {
            if (extension_loaded('openssl')) {
                return file_get_contents($url);
            } else {
                throw new Exception('unopen openssl extension');
            }
        } elseif ($allow_url_fopen && empty($post) && empty($cookie)
            && in_array('http', $stream_wraps)
        ) {
            // 尝试连接
            $opts = array('http' => array('method' => 'GET', 'timeout' => $timeout));
            $context = stream_context_create($opts);
            $html = file_get_contents($url, false, $context);
            return self::convert_html_charset($html, $charset);
        } else {
            return FALSE;
        }
    }

    /**
     * gzdecode
     *
     * @param $data
     * @return string
     */
    private static function gzdecode($data) {
        return gzinflate(substr($data, 10, -8));
    }

    /**
     * convert html charset (detect html charset)
     *
     * @param        $html
     * @param        $charset
     * @param string $tocharset
     * @return string
     */
    private static function convert_html_charset($html, $charset, $tocharset = 'utf-8') {

        //取html中的charset
        $detect_charset = '';
        //html file
        if ($charset) {
            //优先取 http header中的charset
            $detect_charset = $charset;
        } else {
            if (stripos($html, '<meta') !== false) {
                if (strpos($html, 'charset=') !== false) {
                    $head = self::mask_match(strtolower($html), '(*)</head>');
                    if ($head) {
                        $head = strtolower($head);
                        $head = self::reg_replace($head, array(
                            '<script(*)/script>' => '',
                            '<style(*)/style>' => '',
                            '<link(*)>' => '',
                            "\r" => '',
                            "\n" => '',
                            "\t" => '',
                            " " => '',
                            "'" => ' ',
                            "\"" => ' ',
                        ));
                        preg_match_all('/charset\s*?=\s*?([\-\w]+)/', $head, $matches);
                    } else {
                        preg_match_all('/<meta[^>]*?content=("|\'|).*?\bcharset=([\w\-]+)\b/is', $html, $matches);
                    }

                    if (isset($matches[1][0]) && !empty($matches[1][0])) {
                        $detect_charset = $matches[1][0];
                    }
                }
            }
            //xml file
            if (stripos($html, '<?xml') !== false) {
                //<?xml version="1.0" encoding="UTF-8"
                if (stripos($html, 'encoding=') !== false) {
                    $head = self::mask_match($html, '<' . '?xml(*)?' . '>');
                    preg_match_all('/encoding=["\']?([-\w]+)/is', $head, $matches);
                    if (isset($matches[1][0]) && !empty($matches[1][0])) {
                        $detect_charset = $matches[1][0];
                    }
                }
            }
        }
        //alias
        if (strtolower($detect_charset) == 'iso-8859-1') {
            $detect_charset = 'gbk';
        }
        if ($detect_charset) {
            //return mb_convert_encoding($html, $detect_charset, $tocharset);
            return iconv($detect_charset . '//ignore', $tocharset . '//ignore', $html);
        } else {
            return $html;
        }
    }


    /**
     * multi thread fetch url(only support curl)
     *
     * @param $urls
     * @return array
     * @throws Exception
     */
    public static function multi_fetch_url($urls) {
        if (!function_exists('curl_multi_init')) {
            $data = array();
            foreach ($urls as $k => $url) {
                $data[$k] = self::fetch_url($url);
            }
            return $data;
        }

        $multi_handle = curl_multi_init();
        $conn = $data = array();
        foreach ($urls as $i => $url) {
            $conn[$i] = curl_init($url);
            curl_setopt($conn[$i], CURLOPT_ENCODING, '');
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
            $timeout = 3;
            curl_setopt($conn[$i], CURLOPT_CONNECTTIMEOUT, $timeout); // 超时 seconds
            curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, 1);
            curl_multi_add_handle($multi_handle, $conn[$i]);
        }
        do {
            $mrc = curl_multi_exec($multi_handle, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active and $mrc == CURLM_OK) {
            if (curl_multi_select($multi_handle) != -1) {
                do {
                    $mrc = curl_multi_exec($multi_handle, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        foreach ($urls as $i => $url) {
            $data[$i] = curl_multi_getcontent($conn[$i]);
            curl_multi_remove_handle($multi_handle, $conn[$i]);
            curl_close($conn[$i]);
        }
        return $data;
    }
}

?>