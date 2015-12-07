<?php
use Cake\Routing\Router;

Router::plugin('AdaptiveImages', function ($routes) {
    $routes->fallbacks('DashedRoute');
    
    Router::scope('/', function ($routes) {
        $routes->connect('/adaptive_images/*', ['plugin' => 'AdaptiveImages', 'controller' => 'AdaptiveImages', 'action' => 'loadImage']);
        $routes->connect('/adaptive_images/show_semantic_types', ['plugin' => 'AdaptiveImages', 'controller' => 'AdaptiveImages', 'action' => 'showSemanticTypes']);
        $routes->connect('/adaptive_images/clear_cache/*', ['plugin' => 'AdaptiveImages', 'controller' => 'AdaptiveImages', 'action' => 'clearCache']);
    });
    
});
