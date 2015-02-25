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
 * Class OSSExtraDocumentFieldsEvent
 * @package OpenSearchServerSearch\Event
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class OSSExtraDocumentFieldsEvent extends ActionEvent
{
    /** @var Product */
    protected $product;

    /** @var array */
    protected $fields = [];

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

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return array
     */
    public function addFields($name, $value, $boost = null)
    {
        $this->fields[] = [
            'name' => $name,
            'value' => $value,
            'boost' => $boost
        ];
    }
}
