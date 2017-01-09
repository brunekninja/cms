<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\feeds;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\models\Url;
use DateTime;
use yii\base\Component;
use Zend\Feed\Reader\Exception\RuntimeException;
use Zend\Feed\Reader\Reader;

/**
 * The Feeds service provides APIs for fetching remote RSS and Atom feeds.
 *
 * An instance of the Feeds service is globally accessible in Craft via [[Application::feeds `Craft::$app->getFeeds()`]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class Feeds extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Fetches and parses an RSS or Atom feed, and returns its items.
     *
     * Each element in the returned array will have the following keys:
     *
     * - **authors** – An array of the item’s authors, where each sub-element has the following keys:
     *     - **name** – The author’s name
     *     - **url** – The author’s URL
     *     - **email** – The author’s email
     * - **categories** – An array of the item’s categories, where each sub-element has the following keys:
     *     - **term** – The category’s term
     *     - **scheme** – The category’s scheme
     *     - **label** – The category’s label
     * - **content** – The item’s main content.
     * - **contributors** – An array of the item’s contributors, where each sub-element has the following keys:
     *     - **name** – The contributor’s name
     *     - **url** – The contributor’s URL
     *     - **email** – The contributor’s email
     * - **date** – A [[DateTime]] object representing the item’s date.
     * - **dateUpdated** – A [[DateTime]] object representing the item’s last updated date.
     * - **permalink** – The item’s URL.
     * - **summary** – The item’s summary content.
     * - **title** – The item’s title.
     *
     * @param string $url           The feed’s URL.
     * @param int    $limit         The maximum number of items to return. Default is 0 (no limit).
     * @param int    $offset        The number of items to skip. Defaults to 0.
     * @param string $cacheDuration Any valid [PHP time format](http://www.php.net/manual/en/datetime.formats.time.php).
     *
     * @return array|string The list of feed items.
     * @throws \Zend\Feed\Reader\Exception\RuntimeException
     */
    public function getFeedItems($url, $limit = null, $offset = null, $cacheDuration = null)
    {
        // Prevent $limit and $offset from being any empty value besides 0
        $limit = ($limit ?: 0);
        $offset = ($offset ?: 0);

        $return = [];

        // Key based on the classname, url, limit and offset.
        $key = md5(Feeds::className().'.'.$url.'.'.$limit.'.'.$offset);

        // See if we have this cached already.
        if (Craft::$app->getCache()->get($key) === false) {
            if ($cacheDuration === null) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $cacheDuration = Craft::$app->getConfig()->getCacheDuration();
            } else {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $cacheDuration = DateTimeHelper::timeFormatToSeconds($cacheDuration);

                if ($cacheDuration === null) {
                    $cacheDuration = 0;
                }
            }

            // Potentially long-running request, so close session to prevent session blocking on subsequent requests.
            Craft::$app->getSession()->close();

            Reader::setHttpClient(new GuzzleClient());

            try {
                $items = Reader::import($url);
            } catch (RuntimeException $e) {
                Craft::warning('There was a problem parsing the feed: '.$e->getMessage(), __METHOD__);

                return [];
            }

            foreach ($items as $item) {
                /** @var \Zend\Feed\Reader\Entry\EntryInterface $item */
                // Validate the permalink
                $permalink = $item->getPermalink();

                if ($permalink) {
                    $urlModel = new Url();
                    $urlModel->url = $permalink;

                    if (!$urlModel->validate()) {
                        Craft::info('An item was omitted from the feed ('.$url.') because its permalink was an invalid URL: '.$permalink, 'application');
                        continue;
                    }
                }

                $date = $item->getDateCreated()->format('U');
                $dateUpdated = $item->getDateModified()->format('U');

                $return[] = [
                    'authors' => $this->_getItemAuthors($item->getAuthors()),
                    'categories' => $this->_getItemCategories($item->getCategories()),
                    'content' => $item->getContent(),
                    // See: https://github.com/zendframework/zendframework/issues/2969
                    // and https://github.com/zendframework/zendframework/pull/3570
                    'contributors' => $this->_getItemAuthors($item->getAuthors()),
                    'date' => $date ? new DateTime('@'.$date) : null,
                    'dateUpdated' => $dateUpdated ? new DateTime('@'.$dateUpdated) : null,
                    'permalink' => $item->getPermalink(),
                    'summary' => $item->getDescription(),
                    'title' => $item->getTitle(),
                    'enclosures' => $item->getEnclosure(),
                ];
            }

            if ($limit === 0) {
                $return = array_slice($return, $offset);
            } else {
                $return = array_slice($return, $offset, $limit);
            }

            Craft::$app->getCache()->set($key, $return, $cacheDuration);
        }

        return $return;
    }

    // Private Methods
    // =========================================================================\

    /**
     * Returns an array of authors.
     *
     * @param \stdClass[] $objects
     *
     * @return array
     */
    private function _getItemAuthors($objects): array
    {
        $authors = [];

        if (!empty($objects)) {
            foreach ($objects as $object) {
                $authors[] = [
                    'name' => isset($object['name']) ? $object['name'] : '',
                    'url' => isset($object['uri']) ? $object['uri'] : '',
                    'email' => isset($object['email']) ? $object['email'] : '',
                ];
            }
        }

        return $authors;
    }

    /**
     * Returns an array of categories.
     *
     * @param mixed $objects
     *
     * @return array
     */
    private function _getItemCategories($objects): array
    {
        $categories = [];

        if (!empty($objects)) {
            foreach ($objects as $object) {
                $categories[] = [
                    'term' => isset($object['term']) ? $object['term'] : '',
                    'scheme' => isset($object['scheme']) ? $object['scheme'] : '',
                    'label' => isset($object['label']) ? $object['label'] : '',
                ];
            }
        }

        return $categories;
    }
}
