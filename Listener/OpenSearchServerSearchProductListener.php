<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace OpenSearchServerSearch\Listener;

use DateTime;
use OpenSearchServerSearch\Event\OSSEvents;
use OpenSearchServerSearch\Event\OSSExtraDocumentFieldsEvent;
use OpenSearchServerSearch\Event\OSSIndexDocumentEvent;
use OpenSearchServerSearch\Event\OSSIndexProductEvent;
use OpenSearchServerSearch\Event\OSSRaiseIndexationEvent;
use OpenSearchServerSearch\Helper\OpenSearchServerSearchHelper;
use OpenSearchServerSearch\OpenSearchServerSearch;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Product\ProductEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Map\FeatureProductTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 */
class OpenSearchServerSearchProductListener implements EventSubscriberInterface
{
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::PRODUCT_UPDATE => ['productUpdated', 0],
            TheliaEvents::PRODUCT_CREATE => ['productUpdated', 0],
            OSSEvents::INDEX_PRODUCT => ['indexProduct', 128],
            OSSEvents::RAISE_INDEXING => ['raiseIndexation', 128],
            //TheliaEvents::IMAGE_SAVE => ['updateImage', 0],
            //TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT=> ['indexProduct', 0],
            TheliaEvents::AFTER_DELETEPRODUCT => ['productDeleted', 0]
        );
    }

    public function indexProduct(OSSIndexProductEvent $event)
    {
        $this->doIndex($event->getProduct(), $event->getDispatcher());
    }

    protected function doIndex(Product $product, EventDispatcherInterface $dispatcher)
    {
        $fields = [];

        if (!$product->getVisible()) {
            OpenSearchServerSearchHelper::deleteProduct($product);
        } else {
            $event = new OSSExtraDocumentFieldsEvent($product);
            $dispatcher->dispatch(
                OSSEvents::REQUEST_EXTRA_DOCUMENT_FIELD,
                $event
            );
            OpenSearchServerSearchHelper::indexProduct($product, $event->getFields());
        }
    }

    public function productUpdated(ProductUpdateEvent $event)
    {
        $this->doIndex($event->getProduct(), $event->getDispatcher());
    }

    public function productDeleted(ProductEvent $event)
    {
        OpenSearchServerSearchHelper::deleteProduct($event->getProduct());
    }

    /**
     * Raise an indexation of product
     *
     * @param OSSRaiseIndexationEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function raiseIndexation(OSSRaiseIndexationEvent $event)
    {
        $lastIndexation = intval(ConfigQuery::create()->read(OpenSearchServerSearch::CONFIG_LAST_INDEXATION));

        $lastIndexationDate = new DateTime("NOW");
        if (0 !== $lastIndexation) {
            $lastIndexationDate->setTimestamp($lastIndexation);
        } else {
            $lastIndexationDate->sub(new \DateInterval('P2D'));
        }

        // find products that have been modified since last indexation.
        // we have to check related object i18n
        $allProductsId = [];

        // $product
        $productIds = ProductQuery::create()
            ->filterByVisible(1)
            ->filterByUpdatedAt($lastIndexationDate, Criteria::GREATER_THAN)
            ->select(ProductTableMap::ID)
            ->find()
            ->toArray();

        if (!empty($productIds)) {
            $allProductsId = array_merge($allProductsId, $productIds);
        }

        // feature product
        $productIds = FeatureProductQuery::create()
            ->useProductQuery()
            ->filterByVisible(1)
            ->endUse()
            ->withColumn('MAX(' . FeatureProductTableMap::UPDATED_AT . ')', 'lastUpdated')
            ->addAsColumn('id', FeatureProductTableMap::PRODUCT_ID)
            ->groupBy(FeatureProductTableMap::PRODUCT_ID)
            ->having('MAX(' . FeatureProductTableMap::UPDATED_AT . ') > ?', $lastIndexationDate)
            ->select(['id', 'lastUpdated'])
            ->find()
            ->toArray();

        if (!empty($productIds)) {
            $allProductsId = array_merge($allProductsId, array_column($productIds, 'id'));
        }

        $allProductsId = array_unique($allProductsId);

        if (!empty($allProductsId)) {
            $products = ProductQuery::create()->findPks($allProductsId);

            foreach ($products as $product) {
                $this->doIndex($product, $event->getDispatcher());
            }
        }

        // update the date of the last manual indexation
        $now = new \DateTime('NOW');
        ConfigQuery::create()->write(OpenSearchServerSearch::CONFIG_LAST_INDEXATION, $now->getTimestamp());
    }
}
