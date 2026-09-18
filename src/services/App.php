<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\services;

use craft\base\Component;

class App extends Component
{
    /**
     * @var Translate
     */
    public $translate;

    /**
     * @var Settings
     */
    public $settings;

    /**
     * @var Providers
     */
    public $providers;

    /**
     * @var Content
     */
    public $content;

    /**
     * @var Metrics
     */
    public $metrics;

    public function init(): void
    {
        // Settings first — everything else reads from it.
        $this->settings = new Settings();
        $this->providers = new Providers();
        $this->metrics = new Metrics();
        $this->translate = new Translate();
        $this->content = new Content();
    }
}
