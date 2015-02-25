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

namespace OpenSearchServerSearch\Controller\Front;

use Front\Front;
use OpenSearchServerSearch\Model\OpensearchserverConfigQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Front\BaseFrontController;
use OpenSearchServerSearch\Form\ConfigurationForm;
use OpenSearchServerSearch\OpenSearchServerSearch;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Tools\ModelCriteriaTools;
use Thelia\Tools\URL;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;

/**
 * Class OpenSearchServerSearchFrontController
 * @package OpenSearchServerSearch\Controller\Front
 * @author Alexandre Toyer <alexandre.toyer@open-search-server.com>
 */
class OpenSearchServerSearchFrontController extends BaseFrontController
{
    protected $useFallbackTemplate = true;

    public function search()
    {
        //if search with OSS has not been activated yet in module configuration page OSS is not used
        $searchEnabled = OpensearchserverConfigQuery::read('enable_search');
        if (!$searchEnabled) {
            //display results
            return $this->render('search');
        }
        
        //get keywords
        $request = $this->getRequest();
        $keywords = $request->query->get('q', null);
        
        $sort = $request->query->get('order', null);
        
        $page = $request->query->get('page', 1);
        $limit = $request->query->get('limit', 8);
        $offset = ($limit == 100000) ? 0:($page - 1) * $limit;

        //get locale
        $locale = $request->getSession()->getLang()->getLocale();

        $results = $this->getProducts($keywords, $locale, $sort, $page, $limit, $offset);

        //display results
        return $this->render('oss_results', $results);
    }

    public function autocomplete()
    {
        $request = $this->getRequest();
        $keywords = $request->query->get('query', null);

        $responseData = [
            "query" => $keywords,
            "suggestions" => []
        ];

        $searchEnabled = OpensearchserverConfigQuery::read('enable_search');
        if (!$searchEnabled) {
            return $this->jsonResponse(json_encode($responseData));
        }

        $sort = $request->query->get('order', null);
        $page = 1;
        $limit = $request->query->get('limit', 20);
        $offset = 0;

        //get locale
        $locale = $request->getSession()->getLang()->getLocale();

        $results = $this->getProducts($keywords, $locale, $sort, $page, $limit, $offset);
        $ids = explode(',', $results['ids']);

        if (!empty($ids)) {
            $suggestions = $this->buildQuery($ids, $locale);
            $responseData['suggestions'] = $suggestions;
        }

        //display results
        return $this->jsonResponse(json_encode($responseData));
    }

    protected function buildQuery($ids, $locale)
    {
        $query = ProductQuery::create();

        ModelCriteriaTools::getI18n(
            true,
            null,
            $query,
            $locale,
            ['TITLE', 'CHAPO'],
            null,
            'ID'
        );

        $products = $query
            ->filterById($ids)
            ->filterByVisible(1)
            ->find();

        $suggestions = [];
        /** @var Product $product */
        foreach ($products as $product) {
            $suggestions[] = [
                "value" => $product->getVirtualColumn('i18n_TITLE'),
                "data" => [
                    "id" => $product->getId(),
                    "chapo" => $product->getVirtualColumn('i18n_CHAPO'),
                    "url" => $product->getUrl($locale),
                    "group" => 'product'
                ]
            ];
        }

        return $suggestions;
    }

    protected function getProducts($keywords, $locale = null, $sort = null, $page = 1, $limit = 8, $offset = 0)
    {
        $lang = $this->fixLang($locale);

        $index = OpensearchserverConfigQuery::read('index_name');
        $queryTemplate = OpensearchserverConfigQuery::read('query_template');

        //create handler for requests
        $oss_api = \OpenSearchServerSearch\Helper\OpenSearchServerSearchHelper::getHandler();

        //create search request
        $request = new \OpenSearchServer\Search\Field\Search();
        $request->index($index)
            ->template($queryTemplate)
            ->start($offset)
            ->rows($limit)
            //set lang of keywords
            ->lang($lang)
            //filter to get only documents with current locale
            ->filterField('locale', $locale, \OpenSearchServer\Request::OPERATOR_OR)
            ->enableLog()
            ->query($keywords);

        //handle sorting
        switch ($sort) {
            case 'alpha':
                $request->sort('titleSort', \OpenSearchServer\Search\Search::SORT_ASC);
                break;
            case 'alpha_reverse':
                $request->sort('titleSort', \OpenSearchServer\Search\Search::SORT_DESC);
                break;
            case 'min_price':
                $request->sort('price', \OpenSearchServer\Search\Search::SORT_ASC);
                break;
            case 'max_price':
                $request->sort('price', \OpenSearchServer\Search\Search::SORT_DESC);
                break;
        }

        //send query
        $response = $oss_api->submit($request);

        $ids = [];
        foreach ($response->getResults() as $result) {
            $ids[] = $result->getField('id');
        }

        //number of pages
        $numberOfPages = ($limit > 0) ? round($response->getTotalNumberFound()/$limit) : 1;

        //display results
        return [
            'module_code' => 'OpenSearchServerSearch',
            'keywords' => $keywords,
            'total' => $response->getTotalNumberFound(),
            'results' => $response->getResults(),
            'ids' => implode(',', $ids),
            'numberOfPages' => $numberOfPages,
            'sort' => $sort,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'locale' => $locale
        ];

    }

    /**
     * @param $locale
     * @return string
     */
    protected function fixLang($locale)
    {
        //fix bug with FR locale?
        if ($locale == 'fr_FR') {
            $locale = array('fr_FR', 'fr_Fr');
            $lang = \OpenSearchServer\Request::LANG_FR;
        }

        if (!isset($lang)) {
            switch ($locale) {
                case 'en_EN':
                case 'en_US':
                    $lang = \OpenSearchServer\Request::LANG_EN;
                    break;
                case 'es_ES':
                    $lang = \OpenSearchServer\Request::LANG_ES;
                    break;
                case 'it_IT':
                    $lang = \OpenSearchServer\Request::LANG_IT;
                    break;
                case 'ru_RU':
                    $lang = \OpenSearchServer\Request::LANG_RU;
                    break;
                default:
                    $lang = \OpenSearchServer\Request::LANG_UNDEFINED;
                    break;
            }
            return $lang;
        }
        return $lang;
    }
}
