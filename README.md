# AdaptiveImages plugin for CakePHP

## Requirements
PHP extension - Imagick
```
sudo apt-get install php5-imagick
```

## Installation



You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require e2e4gu/adaptive-image-plugin
```
Add to /config/bootstrap.php
```
Plugin::load('AdaptiveImages', ['autoload' => true, 'routes' => true]);
```
or 
```
Plugin::loadAll();
```
Add to main layout <head> tag
```
<?= $this->Html->script('AdaptiveImages.client_screen') ?>
```
Helper includes in controller. Helper need for change src images path from original to cached.
```
public $helpers = ['AdaptiveImages.AdaptiveImg'];
```
Helper syntax. Your also can use Html helper`s options if you need it:
```
echo $this->AdaptiveImg->image('yourimage.jpg', ['semanticType' => 'original']);
```
You can add semanticTypes and resolutionBreakpoints in:
```
<your_local_project_dir>/vendor/e2e4gu/adaptive-image-plugin/config/adaptive_image_config.php
<your_local_project_dir>/config/adaptive_image_config.php
```
Config config syntax:
```
<semanticType> => [
    <breakpoint-001> => [
         <scale-method> => [
                <param-001> => <value-001>
         ]
    ]
]
```
