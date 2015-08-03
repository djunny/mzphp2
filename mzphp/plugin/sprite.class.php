<?php

class sprite {

    private static $path;

    private static $matte;

    private static $output;

    static function process($conf) {
        self::$path = $conf['path'];
        self::$matte = $conf['matte'] ? $conf['matte'] : 'transparent';

        // /home/www/sprite without extension
        self::$output = $conf['output'];

        $img_list = array();

        if (!self::$output || !self::$path) {
            return false;
        }

        if (!is_dir(self::$path)) {
            return false;
        } else if ($dh = opendir(self::$path)) {
            $imgs = array('n' => array(), 'y' => array(), 'x' => array(), 'r' => array());
            while (($file = readdir($dh)) !== false) {
                $ext = strtolower(substr($file, strrpos($file, '.') + 1));
                $basename = basename($file, '.' . $ext);
                if (in_array($ext, array('png', 'gif', 'jpg', 'jpeg'))) {
                    $is_match = preg_match('/-([nxy])(-pl(\d+))?(-pr(\d+))?$/i', $basename, $matches);
                    if (!$is_match) {
                        $matches = array(0, 'n', 0, 0, 0, 0,);
                        $is_match = 1;
                    }
                    if ($is_match) {
                        list($null, $type, $null, $padding_left, $null, $padding_right) = array_pad($matches, 6, 0);
                        $imgs[$type][$file] = self::image_get_info(self::$path . '/' . $file) + compact('padding_left', 'padding_right');
                        unset($matches, $null, $type, $padding_left, $padding_right); // free memory
                    }
                }
            }
            closedir($dh);
            unset($dh, $file, $ext, $basename); // free memory
        }
        if (!count($imgs)) {
            return false;
        }
        // start with no-repeat images
        // @TODO: build an algorithm to optimize this space by packing odd shaped images as tightly as possible into a corner; like Tetris.
        $x = 0;
        $y = 0;
        $sass = $css = array('n' => '', 'y' => '', 'x' => '');
        $css['n'] = '';
        $sass['n'] = '';
        $base_name = basename(self::$output);
        $img_path = $base_name . '.png';
        foreach (array_keys((array)$imgs['n']) as $k) {
            $img = &$imgs['n'][$k];
            $x += (int)$img['padding_left'];
            $img['x'] = $x;
            $x += $img['width'] + (int)$img['padding_right'];
            $img['y'] = 0;
            $y = max($y, $img['height']);
            $css['n'] .= '.' . str_replace('.', '-', $k) . '  {' . "\n" .
                '  background:url(' . $img_path . ') no-repeat ' . self::px($img['x'] * -1) . ' ' . self::px($img['y'] * -1) . '; width:' . self::px($img['width']) . '; height:' . self::px($img['height']) . ';' . "\n" .
                '}' . "\n";
        }
        if ($css['n']) {
            $css['n'] = '/* n.png *' . '/' . "\n" . $css['n'];
            // save sprites
            self::image_save($imgs['n'], self::$path, self::$output . '.png', $x, $y, 'n', self::$matte);
            $img_list[] = self::$output . '.png';
        }

        $x = 0;
        $y = 0;
        $img_path = $base_name . '-y.png';
        foreach (array_keys((array)$imgs['y']) as $k) {
            $img =& $imgs['y'][$k];
            $x += (int)$img['padding_left'];
            $img['x'] = $x;
            $x += $img['width'] + (int)$img['padding_right'];
            $img['y'] = 0;
            $y = max($y, $img['height']);
            $css['y'] .= '.' . str_replace('.', '-', $k) . '  {' . "\n" .
                '  background:url(' . $img_path . ') repeat-y ' . self::px($img['x'] * -1) . ' ' . self::px($img['y'] * -1) . '; width:' . self::px($img['width']) . ';' . "\n" .
                '}' . "\n";
        }
        if ($css['y']) {
            $css['y'] = "\n\n" . '/* y.png *' . '/' . "\n" . $css['y'];
            // save sprites
            self::image_save($imgs['y'], self::$path, self::$output . '-y.png', $x, $y, 'y', self::$matte);
            $img_list[] = self::$output . '-y.png';
        }

        $x = 0;
        $y = 0;
        $img_path = $base_name . '-x.png';
        foreach (array_keys((array)$imgs['x']) as $k) {
            $img =& $imgs['x'][$k];
            $img['x'] = 0;
            $x = max($x, $img['width']);
            $img['y'] = $y;
            $y += $img['height'];
            $css['x'] .= '.' . str_replace('.', '-', $k) . '  {' . "\n" .
                '  background:url(' . $img_path . ') repeat-x ' . self::px($img['x'] * -1) . ' ' . self::px($img['y'] * -1) . '; height:' . self::px($img['height']) . ';' . "\n" .
                '}' . "\n";
        }
        if ($css['x']) {
            $css['x'] = "\n\n" . '/* x.png *' . '/' . "\n" . $css['x'];
            // save sprites
            self::image_save($imgs['x'], self::$path, self::$output . '-x.png', $x, $y, 'x', self::$matte);
            $img_list[] = self::$output . '-x.png';
        }

        return array(
            'css' => implode('', $css),
            'img' => $img_list,
        );
    }


    static function px($n) {
        return $n ? ((int)$n) . 'px' : 0;
    }

    /**
     * Save a sprite image.
     *
     * @param        $imgs
     * @param        $path
     * @param        $filename
     * @param        $x
     * @param        $y
     * @param        $type
     * @param string $matte
     */
    static function image_save($imgs, $path, $filename, $x, $y, $type, $matte = 'transparent') {
        if (!$x || !$y) return; // abort if image dimensions are invalid
        // create new [blank] image
        $im = imagecreatetruecolor($x, $y) or die("Cannot Initialize new GD image stream");
        if ($matte == 'transparent') {
            // apply PNG 24-bit transparency to background
            $transparency = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagealphablending($im, FALSE);
            imagefilledrectangle($im, 0, 0, $x, $y, $transparency);
            imagealphablending($im, TRUE);
            imagesavealpha($im, TRUE);
        } else {
            // apply solid color background
            list($r, $g, $b) = array_pad(explode(',', $matte), 3, 255);
            $color = imagecolorallocate($im, $r, $g, $b);
            imagefilledrectangle($im, 0, 0, $x, $y, $color);
        }
        // overlay all source image onto single destination sprite image
        foreach ($imgs as $file => $img) {
            if (isset($img['extension'], $img['x'], $img['y'], $img['width'], $img['height'])) {
                self::image_overlay(
                    $im,
                    $path . $file,
                    $img['extension'],
                    $type == 'x' ? 0 : $img['x'], // dst_x
                    $type == 't' ? 0 : $img['y'], // dst_y
                    0, // src_x
                    0, // src_y
                    $type == 'x' ? $x : $img['width'], // dst_w
                    $type == 'y' ? $y : $img['height'], // dst_h
                    $img['width'], // src_w
                    $img['height'] // src_h
                );
            }
        }
        // save sprite image prefix as PNG
        imagepng($im, $filename);
        imagedestroy($im);
    }

    /**
     * Overlay a source image on a destination image at a given location.
     *
     * @param $dst_im
     * @param $src_path
     * @param $src_ext
     * @param $dst_x
     * @param $dst_y
     * @param $src_x
     * @param $src_y
     * @param $dst_w
     * @param $dst_h
     * @param $src_w
     * @param $src_h
     */
    static function image_overlay(&$dst_im, $src_path, $src_ext, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) {
        $src_im = self::image_gd_open($src_path, $src_ext); // load source image
        imagecopyresampled($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h); // overlay source image on destination image
        imagedestroy($src_im);

    }

    /**
     * Get details about an image.
     *
     * @param $file
     * @return array|bool
     */
    static function image_get_info($file) {
        if (!is_file($file)) {
            return FALSE;
        }
        $details = FALSE;
        $data = @getimagesize($file);
        $file_size = @filesize($file);
        if (isset($data) && is_array($data)) {
            $extensions = array('1' => 'gif', '2' => 'jpg', '3' => 'png');
            $extension = array_key_exists($data[2], $extensions) ? $extensions[$data[2]] : '';
            $details = array('width' => $data[0],
                'height' => $data[1],
                'extension' => $extension,
                'file_size' => $file_size,
                'mime_type' => $data['mime']);
        }
        return $details;
    }

    /**
     * GD helper static function to create an image resource from a file.
     *
     * @param $file
     * @param $extension
     * @return bool
     */
    static function image_gd_open($file, $extension) {
        $extension = str_replace('jpg', 'jpeg', $extension);
        $open_func = 'imageCreateFrom' . $extension;
        if (!function_exists($open_func)) {
            return FALSE;
        }
        return $open_func($file);
    }
}

?>