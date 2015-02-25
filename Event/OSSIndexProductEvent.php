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
use Thelia\Model\Product;

/**
 * Class OSSIndexProductEvent
 * @package OpenSearchServerSearch\Event
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class OSSIndexProductEvent extends ActionEvent
{
    /** @var Product */
    protected $product;

    public function __construct($product)
    {
        $this->product = $product;
    }

    /**
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param Product $product
     */
    public function setProduct($product)
    {
        $this->product = $product;
    }
}
