<?php
namespace AdaptiveImages\Test\TestCase\View\Helper;

use AdaptiveImages\View\Helper\AdaptiveImgHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;

/**
 * AdaptiveImages\View\Helper\AdaptiveImgHelper Test Case
 */
class AdaptiveImgHelperTest extends TestCase
{

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $view = new View();
        $this->AdaptiveImg = new AdaptiveImgHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->AdaptiveImg);

        parent::tearDown();
    }

    /**
     * Test initial setup
     *
     * @return void
     */
    public function testInitialization()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
