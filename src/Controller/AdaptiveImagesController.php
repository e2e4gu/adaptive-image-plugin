<?php
namespace AdaptiveImages\Controller;

use AdaptiveImages\Controller\AppController;
use AdaptiveImages\Model\Imagine;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\Log\Log;

/**
 * AdaptiveImages Controller
 *
 * @property \AdaptiveImages\Model\Table\AdaptiveImagesTable $AdaptiveImages
 */
class AdaptiveImagesController extends AppController
{
    public $helpers = ['Html'];

    /* CONFIG ----------------------------------------------------------------------------------------------------------- */

    protected $_resolutions = [1382, 992, 768, 480]; // the resolution break-points to use (screen widths, in pixels)
    protected $_cachePath = "ai-cache"; // where to store the generated re-sized images. Specify from your $_pluginDocumentRoot!
    protected $_jpgQuality = 75; // the quality of any generated JPGs on a scale of 0 to 100
    protected $_sharpen = true; // Shrinking images can blur details, perform a sharpen on re-scaled images?
    protected $_watchCache = true; // check that the adapted image isn't stale (ensures updated source images are re-cached)
    protected $_browserCache = 604800; // How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
    protected $_configFilename = 'adaptive_image_config'; // without extension; get config from ./config/ or , if not exists, from ./plugins/AdaptiveImages/config/
    protected $_documentRoot = WWW_ROOT;
    protected $_resolution = false;
    protected $_cacheClearSecretWord = 'jumanji';
    
    /* END CONFIG ------------------------------------------------------------------------------------------------------- */
    protected $_requestedUri;
    protected $_requestedFile;
    protected $_sourceFile;
    protected $_configData;
    protected $_pluginDocumentRoot;

    /**
     * Initiaization
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        
        $this->_pluginDocumentRoot = Plugin::path('AdaptiveImages') . 'webroot';

        //load config from ./config/ if exists
        if (file_exists(ROOT . DS . 'config' . DS . $this->_configFilename . '.php')) {
            Configure::load($this->_configFilename);
        } else {
            Configure::load('AdaptiveImages.' . $this->_configFilename);
        }
        
        $this->_configData = Configure::read('AdaptiveImagesPlugin');
        $this->Imagine = new Imagine();
    }

    /**
     * Get secret word
     *
     * @return string
     */
    public function getSecretWord()
    {
        return $this->_cacheClearSecretWord;
    }
    
    /**
     * Get relative cache path
     *
     * @return string
     */
    public function getCachePath()
    {
        return $this->_cachePath;
    }
    
    /**
     * Return all semantic types from config
     *
     * @return array
     */
    public function showSemanticTypes()
    {
        $this->viewBuilder()->layout('ajax');
        
        $this->set('semanticTypes', $this->_configData);
    }
    
    /**
     * Remove cache folder
     *
     * @return bool
     */
    public function removeCacheFolder()
    {
        $cacheFolderPath = $this->_pluginDocumentRoot . DS . $this->_cachePath . DS;
        $cacheFolder = new Folder($cacheFolderPath);
        
        if ($cacheFolder->delete()) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Check if cache folder exists
     *
     * @return bool
     */
    public function isCacheDirExists()
    {
        $cacheFolderPath = $this->_pluginDocumentRoot . DS . $this->_cachePath . DS;
        $cacheFolder = new Folder($cacheFolderPath);
        if (!is_null($cacheFolder->path)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * passiveCaching method
     *
     * Cache all resolutions of source image to cache folder
     * settings gets from $this->_configData
     *
     * @param string $filePath path to file
     * @param string $semanticType semantic type of file
     * @return void
     */
    public function passiveCaching($filePath, $semanticType = 'default')
    {
        $this->autoRender = false;

        $file = new File($filePath);
        if ($file->exists()) {
            $this->_sourceFile = $file->path;
            $this->_requestedUri = $file->name;
        }
        
        if (isset($this->_configData[$semanticType])) {
            $this->_resolutions = array_keys($this->_configData[$semanticType]);
        } else {
            header("Status: Wrong semantic type");
            exit();
        }
        
        // does the $this->_cachePath directory exist already?
        if (!is_dir("$this->_pluginDocumentRoot/$this->_cachePath")) { // no
            if (!mkdir("$this->_pluginDocumentRoot/$this->_cachePath", 0755, true)) { // so make it
                if (!is_dir("$this->_pluginDocumentRoot/$this->_cachePath")) { // check again to protect against race conditions
                    // uh-oh, failed to make that directory
                    header("Failed to create cache directory at: $this->_pluginDocumentRoot/$this->_cachePath");
                    exit();
                }
            }
        }
        
        // check if the file exists at all
        if (!file_exists($this->_sourceFile)) {
            header("Status: 404 Not Found; file = {$this->_sourceFile}");
            exit();
        }
        
        
        
        /* if the requested URL starts with a slash, remove the slash */
        if (substr($this->_requestedUri, 0, 1) == "/") {
            $this->_requestedUri = substr($this->_requestedUri, 1);
        }

        /* whew might the cache file be? */

        // $currentResolution = (string) $this->_resolution;

        if (isset($this->_configData[$semanticType])) {
            foreach ($this->_configData[$semanticType] as $resolution => $imageOperations) {
                if ($imageOperations == 'original') {
                    continue;
                }
                $cacheFile = $this->_pluginDocumentRoot . DS . $this->_cachePath . DS . $semanticType . DS . $resolution . DS . $this->_requestedUri;

                if (file_exists($cacheFile)) { // it exists cached at that size
                    $this->_createCacheDir($cacheFile);
                    $this->_refreshCache($this->_sourceFile, $cacheFile, $imageOperations);
                } else {
                    $this->_createCacheDir($cacheFile);
                    $this->Imagine->processImage($this->_sourceFile, $cacheFile, [], $imageOperations);
                }
            }
        }
    }
    
    /**
     * Cache and return cached image in realtime
     *
     * You can pass semanticType='original' for return original image
     *
     * @return void
     */
    public function loadImage()
    {
        $this->autoRender = false;

        $semanticType = 'default';
        if (isset($this->request->query['semanticType'])) {
            $semanticType = $this->request->query['semanticType'];
        }
        
        if (isset($this->request->query['path'])) {
            $this->_sourceFile = $this->_documentRoot . $this->request->query['path'];
            $file = new File($this->_sourceFile);
            $this->_requestedUri = $file->name;
        }
        
        //return original image
        if ($semanticType == 'original') {
            $this->_sendImage($this->_sourceFile, $this->_browserCache);
        }

        if (isset($this->_configData[$semanticType])) {
            $this->_resolutions = array_keys($this->_configData[$semanticType]);
        } else {
            $this->_sendErrorImage('Wrong semantic type!');
        }

        /* Does the UA string indicate this is a mobile? */
        if (!$this->_isMobile()) {
            $isMobile = false;
        } else {
            $isMobile = true;
        }

        // does the $this->_cachePath directory exist already?
        // if (!is_dir("$this->_pluginDocumentRoot/$this->_cachePath")) {
        //     $this->_sendErrorImage("Cache folder not exists: $this->_pluginDocumentRoot/$this->_cachePath");
        // }

        // check if the file exists at all
        if (!file_exists($this->_sourceFile)) {
            header("Status: 404 Not Found");
            exit();
        }

        /* Check to see if a valid cookie exists */
        if (isset($_COOKIE['resolution'])) {
            $cookieValue = $_COOKIE['resolution'];

          // does the cookie look valid? [whole number, comma, potential floating number]
            if (! preg_match("/^[0-9]+[,]*[0-9\.]+$/", "$cookieValue")) { // no it doesn't look valid
                setcookie("resolution", "$cookieValue", time() - 100); // delete the mangled cookie
            } else { // the cookie is valid, do stuff with it
                $cookieData = explode(",", $_COOKIE['resolution']);
                $clientWidth = (int)$cookieData[0]; // the base resolution (CSS pixels)
                $totalWidth = $clientWidth;
                $pixelDensity = 1; // set a default, used for non-retina style JS snippet
                if (@$cookieData[1]) { // the device's pixel density factor (physical pixels per CSS pixel)
                    $pixelDensity = $cookieData[1];
                }

                rsort($this->_resolutions); // make sure the supplied break-points are in reverse size order
                $this->_resolution = $this->_resolutions[0]; // by default use the largest supported break-point

                // if pixel density is not 1, then we need to be smart about adapting and fitting into the defined breakpoints
                if ($pixelDensity != 1) {
                    $totalWidth = $clientWidth * $pixelDensity; // required physical pixel width of the image

                  // the required image width is bigger than any existing value in $this->_resolutions
                    if ($totalWidth > $this->_resolutions[0]) {
                        // firstly, fit the CSS size into a break point ignoring the multiplier
                        foreach ($this->_resolutions as $breakPoint) { // filter down
                            if ($totalWidth <= $breakPoint) {
                                $this->_resolution = $breakPoint;
                            }
                        }
                        // now apply the multiplier
                        $this->_resolution = $this->_resolution * $pixelDensity;
                    } else {
                        // the required image fits into the existing breakpoints in $this->_resolutions
                        foreach ($this->_resolutions as $breakPoint) { // filter down
                            if ($totalWidth <= $breakPoint) {
                                $this->_resolution = $breakPoint;
                            }
                        }
                    }
                } else { // pixel density is 1, just fit it into one of the breakpoints
                    foreach ($this->_resolutions as $breakPoint) { // filter down
                        if ($totalWidth <= $breakPoint) {
                            $this->_resolution = $breakPoint;
                        }
                    }
                }
            }
        }

        /* No resolution was found (no cookie or invalid cookie) */
        if (!$this->_resolution) {
            // We send the lowest resolution for mobile-first approach, and highest otherwise
            $this->_resolution = $isMobile ? min($this->_resolutions) : max($this->_resolutions);
        }

        /* if the requested URL starts with a slash, remove the slash */
        if (substr($this->_requestedUri, 0, 1) == "/") {
            $this->_requestedUri = substr($this->_requestedUri, 1);
        }
        
        if ($this->_configData[$semanticType][$this->_resolution] == 'original') {
            $this->_sendImage($this->_sourceFile, $this->_browserCache);
            return;
        }
        /* whew might the cache file be? */
        $cacheFile = $this->_pluginDocumentRoot . DS . $this->_cachePath . DS . $semanticType . DS . $this->_resolution . DS . $this->_requestedUri;


        $currentResolution = (string)$this->_resolution;


        /* Use the resolution value as a path variable and check to see if an image of the same name exists at that path */
        if (file_exists($cacheFile)) { // it exists cached at that size
            $this->_sendImage($cacheFile, $this->_browserCache);
            return;
        } else {
            $this->_sendImage($this->_sourceFile, $this->_browserCache);
            return;
        }
    }

    /**
     * Mobile detection
     * NOTE: only used in the event a cookie isn't available.
     * @return string
     */
    protected function _isMobile()
    {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        return strpos($userAgent, 'mobile');
    }

    /**
     * Helper protected function: Send headers and returns an image
     *
     * @param string $filename file name
     * @param string|int $browserCache lifetime|max-age
     * @return void
     */
    protected function _sendImage($filename, $browserCache)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, ['png', 'gif', 'jpeg'])) {
            header("Content-Type: image/" . $extension);
        } else {
            header("Content-Type: image/jpeg");
        }
        header("Cache-Control: private, max-age=" . $browserCache);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $browserCache) . ' GMT');
        header('Content-Length: ' . filesize($filename));

        readfile($filename);
        exit();
    }

    /**
     * Helper protected function: Create and send an image with an error message.
     *
     * @param string $message message that render as image
     * @return void
     */
    protected function _sendErrorImage($message)
    {
        /* get all of the required data from the HTTP request */
        $this->_documentRoot = $_SERVER['DOCUMENT_ROOT'];
        $this->_requestedUri = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        if (isset($this->request->query['path'])) {
            $this->_requestedFile = $this->request->query['path'];
        } else {
            $this->_requestedFile = basename($this->_requestedUri);
        }
        $this->_sourceFile = $this->_documentRoot . $this->_requestedFile;
        if (isset($this->request->query['semanticType'])) {
            $semanticType = $this->request->query['semanticType'];
        } else {
            $semanticType = 'undefined';
        }

        if (!$this->_isMobile()) {
            $isMobile = "FALSE";
        } else {
            $isMobile = "TRUE";
        }

        $im = ImageCreateTrueColor(800, 300);
        $textColor = ImageColorAllocate($im, 233, 14, 91);
        $messageColor = ImageColorAllocate($im, 91, 112, 233);

        ImageString($im, 5, 5, 5, "Adaptive Images encountered a problem:", $textColor);
        ImageString($im, 3, 5, 25, $message, $messageColor);

        ImageString($im, 5, 5, 85, "Potentially useful information:", $textColor);
        ImageString($im, 3, 5, 105, "DOCUMENT ROOT IS: $this->_documentRoot", $textColor);
        ImageString($im, 3, 5, 125, "REQUESTED URI WAS: $this->_requestedUri", $textColor);
        ImageString($im, 3, 5, 145, "REQUESTED FILE WAS: $this->_requestedFile", $textColor);
        ImageString($im, 3, 5, 165, "SOURCE FILE IS: $this->_sourceFile", $textColor);
        ImageString($im, 3, 5, 185, "DEVICE IS MOBILE? $isMobile", $textColor);
        ImageString($im, 3, 5, 205, "SEMANTIC TYPE IS: $semanticType", $textColor);

        header("Cache-Control: no-store");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1000) . ' GMT');
        header('Content-Type: image/jpeg');
        ImageJpeg($im);
        ImageDestroy($im);
        exit();
    }

    /**
     * Sharpen images protected function
     *
     * @param int $intOrig int original
     * @param int $intFinal int final
     * @return int
     */
    protected function _findSharp($intOrig, $intFinal)
    {
        $intFinal = $intFinal * (750.0 / $intOrig);
        $intA = 52;
        $intB = -0.27810650887573124;
        $intC = .00047337278106508946;
        $intRes = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
        return max(round($intRes), 0);
    }

    /**
     * Refreshes the cached image if it's outdated
     *
     * @param string $sourceFile source file
     * @param string $cacheFile cache file
     * @param int $imageOperations config
     * @return Image
     */
    protected function _refreshCache($sourceFile, $cacheFile, $imageOperations)
    {
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        return $this->Imagine->processImage($sourceFile, $cacheFile, [], $imageOperations);
    }
    
    /**
     * Regenerate image if img-sizes changed
     *
     * @param string $cacheFile path to cached image
     * @param array $imageOperations array with methods and values for manipulate image
     * @return void
     */
    protected function _clearCache($cacheFile, $imageOperations)
    {
        $beforeScalingImage = $this->Imagine->imagineObject()->open($cacheFile);
        
        $afterScalingImage = $this->Imagine->processImage(
            $this->_sourceFile,
            null,
            [],
            $imageOperations
        );
        
        $beforeScalingImageSizes = $this->Imagine->getImageSize($beforeScalingImage);
        $afterScalingImageSizes = $this->Imagine->getImageSize($afterScalingImage);
        
        if ($beforeScalingImageSizes !== $afterScalingImageSizes) {
            $this->Imagine->processImage($this->_sourceFile, $cacheFile, [], $imageOperations);
        }
    }

    /**
     * PHP delete function that deals with directories recursively
     *
     * @param string $target path to folder which should be deleted
     * @return void
     */
    protected function _deleteFiles($target)
    {
        if (is_dir($target)) {
            $files = glob($target . '*', GLOB_MARK); //GLOB_MARK adds a slash to directories returned
            
            foreach ($files as $file) {
                $this->_deleteFiles($file);
            }

            rmdir($target);
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
    
    /**
     * Create cache folder for cached images
     *
     * @param string $cacheFile path to cache file
     * @return void
     */
    protected function _createCacheDir($cacheFile)
    {
        $dirName = dirname($cacheFile);
        $cacheDir = new Folder($dirName, true, 0755);

        if (!is_dir($cacheDir->path)) {
            $this->_sendErrorImage("Failed to create cache directory: {$cacheDir->path}");
        }
    }
}
