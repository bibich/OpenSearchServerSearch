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

/**
 * Class OSSEvents
 * @package OpenSearchServerSearch\Event
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class OSSEvents
{
    const INDEX_PRODUCT = 'action.oss.index-product';
    const RAISE_INDEXING = 'action.oss.raise-indexing';
    const REQUEST_EXTRA_DOCUMENT_FIELD = 'action.oss.request-extra-document-fields';
    const REQUEST_OSS_QUERY_TEMPLATE = 'action.oss.request-oss-query-template';
    const REQUEST_OSS_SCHEMA = 'action.oss.request-oss-schema';
}
