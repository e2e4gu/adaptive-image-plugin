<?php
namespace AdaptiveImages\Shell;

use AdaptiveImages\Controller\AdaptiveImagesController;
use Cake\Console\Shell;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;

/**
 * CacheImages shell command.
 */
class CacheImagesShell extends Shell
{
    /**
     * initialize method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();
        $this->loadModel('Users');
        $this->adaptiveImagesController = new AdaptiveImagesController();
    }
    
    /**
     * getOptionParser method
     *
     * @return $parser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $this->_io->styles('green_text', ['text' => 'green', 'blink' => true]);
        $this->_io->styles('red_text', ['text' => 'red', 'blink' => true]);
        $this->_io->styles('blue_text', ['text' => 'blue', 'blink' => true]);
                
        $parser->addSubcommand('start_caching', [
            'help' => 'Cache and optimize original image in plugin webroot cache folder',
            'parser' => [
                'description' => [
                    __("<warning>This subcommand uses for cache and optimize all images from "),
                    __("source folder to cache folder. Cache options gets from "),
                    __("adaptive_image_config.php in /config dir. You also can "),
                    __("disable image optimization if argument 'optimization' = false. "),
                    __("If cache folder already exists - all images will be recached. </warning>")
                ],
                'arguments' => [
                    'optimization' => [
                        'help' => __('Enable\Disable image optimization; boolean; <blue_text>(default: true)</blue_text>'),
                        'required' => false
                    ],
                    'src_path' => [
                        'help' => __('Folder for source images, root is : /webroot/img/. <blue_text>(default: \'src_images\')</blue_text>'),
                        'required' => false
                    ]
                ]
            ]
        ]);
        $parser->addSubcommand('clear_cache', [
            'help' => 'Remove cache folder',
            'parser' => [
                'description' => [
                    __("<warning>Remove cache folder with cached images</warning>")
                ]
            ]
        ]);
        $parser->addSubcommand('check_for_img_tags', [
            'help' => 'Show places in code, where don\'t uses AdaptiveImgHelper for images; Search in /src dir',
            'parser' => [
                'description' => [
                    __("<warning>Show places in code, where don't uses AdaptiveImgHelper for images; Search in /src dir</warning>")
                ]
            ]
        ]);
    
        return $parser;
    }

    /**
     * startCaching method
     *
     * Cache\[optimize] images in cache folder
     *
     * @param string $optimization 'true' or 'false'
     * @param string $srcPath folder name for source images
     * @return void
     */
    public function startCaching($optimization = 'true', $srcPath = 'src_images')
    {
        $dir = new Folder(WWW_ROOT . 'img' . DS . $srcPath);
        $files = $dir->findRecursive('.*\.(jpg|jpeg|png|gif|svg)');

        /*
         * Error handler
         */
        if (is_null($dir->path)) {
            $this->error('<red_text>Source folder not exists!</red_text>');
        }
        if ($optimization != 'true' && $optimization != 'false') {
            $this->error('<red_text>Arguments \'optimization\' should equal \'true\' or \'false\'</red_text>');
        }

        /*
         * Caching
         */
        $counter = 1;
        $countFiles = count($files);
        $this->out('<info>Images caching</info>');
        foreach ($files as $file) {
            $file = new File($file);
            $semanticType = explode(DS, $file->Folder()->path);
            $semanticType = $semanticType[count($semanticType) - 1]; //get semantic type name
            $this->adaptiveImagesController->passiveCaching($file->path, $semanticType);
            $this->_io->overwrite($this->progressBar($counter, $countFiles), 0, 50);
            $counter++;
        }

        /*
         * Optimization
         */
        if ($optimization == 'true') {
            $cachePath = $this->adaptiveImagesController->getCachePath();
            $pluginPath = Plugin::path('AdaptiveImages');
            $cacheDir = new Folder($pluginPath . 'webroot' . DS . $cachePath);
            $files = $cacheDir->findRecursive('.*\.(jpg|jpeg|png|gif|svg)');
            $counter = 1;
            $countFiles = count($files);
            $this->out('');
            $this->out('<info>Images optimization</info>');
            foreach ($files as $file) {
                $this->_optimizeImage($file);
                $this->_io->overwrite($this->progressBar($counter, $countFiles), 0, 50);
                $counter++;
            }
            
            $this->hr();
            $this->out('<green_text>Caching and optimization completed!</green_text>');
        } elseif ($optimization == 'false') {
            $this->hr();
            $this->out('<green_text>Caching completed!</green_text>');
        }
    }
    
    /**
     * progressBar method
     *
     * @param int $current current value
     * @param int $total total values
     * @param int $size progressbar length
     * @return string
     */
    public function progressBar($current, $total, $size = 50)
    {
        $barString = '';
        
        $perc = intval(($current / $total) * 100);
        for ($i = strlen($perc); $i <= 4; $i++) {
            $perc = ' ' . $perc;
        }
        $totalSize = $size + $i + 3;
        if ($current > 0) {
            for ($place = $totalSize; $place > 0; $place--) {
                $barString .= "\x08";
            }
        }
        for ($place = 0; $place <= $size; $place++) {
            if ($place <= ($current / $total * $size)) {
                $barString .= "\033[42m \033[0m";
            } else {
                $barString .= "\033[47m \033[0m";
            }
        }
        $barString .= " $perc%";
        if ($current == $total) {
            $barString .= PHP_EOL;
        }
        return $barString;
    }
    
    
    /**
     * _optimizeImage method
     *
     * Cache\[optimize] images in cache folder
     *
     * @param string $imgPath path to source image that will be cached\[optimized]
     * @return void
     */
    protected function _optimizeImage($imgPath = null)
    {
        $factory = new \ImageOptimizer\OptimizerFactory();
        $optimizer = $factory->get();
        $optimizer->optimize($imgPath);
    }
    
    /**
     * clearCache method
     *
     * Remove cache folder with cached images
     *
     * @return void
     */
    public function clearCache()
    {
        $secretWord = $this->adaptiveImagesController->getSecretWord();
        if ($this->adaptiveImagesController->removeCacheFolder()) {
            $this->out('<green_text>Done clearing cache</green_text>');
        } else {
            $this->error('<red_text>Can\'t remove cache folder. Maybe folder not exists or try to execute command with \'sudo\'</red_text>');
        }
    }
    
    /**
     * checkForImgTags method
     *
     * Show places in code, where don't uses AdaptiveImgHelper for images
     *
     * @return void
     */
    public function checkForImgTags()
    {
        $this->out(shell_exec('grep -Rn "<img[^>]*>\|Html->image" ' . ROOT . DS . 'src' . DS . " | awk -F':' '{gsub(\"" . ROOT . "\", \"\", $1); gsub(/^[ \t]+/, \"\", $3); gsub(/[ \t]+$/, \"\", $3); print \"<green_text>\"$1\":</green_text><blue_text>\"$2\"</blue_text>:  \" $3}'"));
    }
}
