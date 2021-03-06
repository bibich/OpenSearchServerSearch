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

namespace OpenSearchServerSearch\Helper;

use OpenSearchServerSearch\Model\OpensearchserverConfigQuery;
use OpenSearchServerSearch\Model\OpensearchserverProduct;
use OpenSearchServerSearch\Model\OpensearchserverProductQuery;
use Thelia\Log\Tlog;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;

/**
 * Class OpenSearchServerSearchHelper
 * @package OpenSearchServerSearch\Helper
 * @author Alexandre Toyer <alexandre.toyer@open-search-server.com>
 */
class OpenSearchServerSearchHelper
{
    public static function getHandler()
    {
        $url = OpensearchserverConfigQuery::read('hostname');
        $login = OpensearchserverConfigQuery::read('login');
        $apiKey = OpensearchserverConfigQuery::read('apikey');

        //create handler for requests
        $ossApi = new \OpenSearchServer\Handler(array('url' => $url, 'key' => $apiKey, 'login' => $login ));

        return $ossApi;
    }

    public static function makeProductUniqueId($locale, Product $product)
    {
        //concatenate locale + ref
        return $locale.'_'.$product->getId();
    }

    public static function indexProduct(Product $product, array $fields = [])
    {
        // Check if the product is visible and activated for indexation
        if (0 === $product->getVisible()) {
            self::deleteProduct($product);
            return true;
        }

        // get the configuration of the product
        $keywords = '';
        $customConfig = OpensearchserverProductQuery::create()->findOneByProductId($product->getId());
        if (null !== $customConfig) {
            if (0 !== $customConfig->getDisabled()) {
                self::deleteProduct($product);
                return true;
            }
            $keywords = $customConfig->getKeywords();
        }

        /************************************
         * Get name of index and handler to work with OSS API
         ************************************/
        $index = OpensearchserverConfigQuery::read('index_name');
        $oss_api = OpenSearchServerSearchHelper::getHandler();

        /************************************
         * Create/update document
         ************************************/
        //get price from first combination SaleElement
        $collSaleElements  = $product->getProductSaleElementss();
        $infos = $collSaleElements->getFirst()->toArray();
        $price  = ProductPriceQuery::create()
            ->findOneByProductSaleElementsId($infos['Id'])
            ->toArray();

        //create one document by translation
        $translations = $product->getProductI18ns();

        //Prepare request for OSS
        $request = new \OpenSearchServer\Document\Put();
        $request->index($index);
        foreach ($translations as $translation) {
            $document = new \OpenSearchServer\Document\Document();
            $productI18nInfos = $translation->toArray();

            switch ($productI18nInfos['Locale']) {
                case 'fr_Fr':
                case 'fr_FR':
                    $document->lang(\OpenSearchServer\Request::LANG_FR);
                    break;
                case 'en_EN':
                case 'en_US':
                    $document->lang(\OpenSearchServer\Request::LANG_EN);
                    break;
                case 'es_ES':
                    $document->lang(\OpenSearchServer\Request::LANG_ES);
                    break;
                case 'it_IT':
                    $document->lang(\OpenSearchServer\Request::LANG_IT);
                    break;
                case 'ru_RU':
                    $document->lang(\OpenSearchServer\Request::LANG_RU);
                    break;
                default:
                    $document->lang(\OpenSearchServer\Request::LANG_UNDEFINED);
                    break;
            }
            
            $document
                ->field(
                    'uniqueId',
                    OpenSearchServerSearchHelper::makeProductUniqueId($productI18nInfos['Locale'], $product)
                )
                ->field('id', $product->getId())
                ->field('title', $productI18nInfos['Title'])
                ->field('locale', $productI18nInfos['Locale'])
                ->field('description', $productI18nInfos['Description'])
                ->field('chapo', $productI18nInfos['Chapo'])
                ->field('price', self::formatPrice($price['Price']))
                ->field('currency', $price['CurrencyId'])
                ->field('reference', $product->getRef())
                ->field('keywords', $keywords);

            // extra fields
            if (!empty($fields)) {
                foreach ($fields as $field) {
                    if (null === $field['locale'] || $productI18nInfos['Locale'] == $field['locale']) {
                        $document->field(
                            $field['name'],
                            $field['value'],
                            array_key_exists('boost', $field) ? $field['boost'] : null
                        );
                    }
                }
            }

            $request->addDocument($document);
        }

        $success = false;

        try {
            $response = $oss_api->submit($request);
            $success = $response->isSuccess();
        } catch (\Exception $ex) {
            Tlog::getInstance()->error(
                sprintf(
                    "OSS Indexation [product:%s] : %s",
                    $product->getId(),
                    $ex
                )
            );
        }

        return $success;

    }
    
    public static function deleteProduct(Product $product)
    {
        /************************************
         * Get name of index and handler to work with OSS API
         ************************************/
        $index = OpensearchserverConfigQuery::read('index_name');
        $oss_api = OpenSearchServerSearchHelper::getHandler();
        
        //delete every versions of this product (all locales)
        $request = new \OpenSearchServer\Document\Delete();
        $request->index($index)
                ->field('id')
                ->value($product->getId());

        $success = false;

        try {
            $response = $oss_api->submit($request);
            $success = $response->isSuccess();
        } catch (\Exception $ex) {
            Tlog::getInstance()->error(
                sprintf(
                    "OSS Indexation delete [product:%s] : %s",
                    $product->getId(),
                    $ex
                )
            );
        }

        return $success;
    }
    
    public static function formatPrice($price)
    {
        return str_replace(' ', '', $price);
    }
}
