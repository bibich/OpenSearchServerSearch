<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace OpenSearchServerSearch\Event;

use Thelia\Core\Event\ActionEvent;

/**
 * Class OSSConfigGetterEvent
 * @package OpenSearchServerSearch\Event
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class OSSConfigGetterEvent extends ActionEvent
{
    /** @var string */
    protected $config;

    /**
     * @return string
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param string $config
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }
}
