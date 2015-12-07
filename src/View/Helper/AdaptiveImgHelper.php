<?php
namespace AdaptiveImages\View\Helper;

use Cake\Core\Configure;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\Network\Response;
use Cake\View\Helper;
use Cake\View\Helper\HtmlHelper;
use Cake\View\StringTemplateTrait;
use Cake\View\View;

/**
 * AdaptiveImg helper
 */
class AdaptiveImgHelper extends HtmlHelper
{

    /**
     * Overloaded Html->image() method
     * change image src for using AdaptiveImages plugin
     *
     * @param string $path Path to the image file, relative to the app/webroot/img/ directory.
     * @param array $options Array of HTML attributes. See above for special options.
     * @return string completed img tag with chenged src
     */
    public function image($path, array $options = [])
    {
        // if ($path[0] == '/') { //absolute
        //     $path = substr($path, 1);
        //     $file = new File(WWW_ROOT . $path);
        // } else { //relative
        //     $file = new File(WWW_ROOT . 'img' . DS . $path);
        // }
        // return dirname($file->path);
        if ($path[0] != '/') {
            $path = '/img/' . $path;
        }
        $path = '/adaptive_images' . '?path=' . $path;
        
        if (isset($options['semanticType'])) {
            $path .= '&semanticType=' . $options['semanticType'];
        }
        
        return parent::image($path, $options);
    }
}
