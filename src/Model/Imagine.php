<?php
/**
 * Copyright 2011-2015, Florian Krämer
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * Copyright 2011-2015, Florian Krämer
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace AdaptiveImages\Model;

use Cake\ORM\Table;

/**
 * @deprecated Use the ImageProcessor class instead.
 */
class Imagine extends Table {

/**
 * Table
 *
 * @var bool|string
 */
    public $useTable = false;

/**
 * Behaviors
 *
 * @var array
 */
    public $actsAs = [
        'Imagine.Imagine'
    ];

    public function initialize(array $config)
    {
        $this->addBehavior('AdaptiveImages.Imagine');
    }

    public function schema($schema = []) {
        return [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string', 'null' => false],
            '_constraints' => [
                'primary' => ['type' => 'primary', 'columns' => ['id']]
            ]
        ];
    }
}
