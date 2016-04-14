<?php

require_once 'Model.php';

/**
 * Model class for feed objects
 */
class ParselyModel extends Model {

  const TABLE_NAME = 'ParselyModel';
  const REQUESTS_COUNT = 'count';

  private $count;

  public function __construct($url, $request_count = false) {
    parent::__construct();
    $this->key_name = sha1($url);
    if (! $request_count) {
      $this->count = $url;
    } else {
      $this->count = $request_count;
    }
  }

  public function getSubscriberUrl() {
    return $this->count;
  }


  protected static function getKindName() {
    return self::TABLE_NAME;
  }

  /**
   * Generate the entity property map from the feed object fields.
   */
  protected function getKindProperties() {
    $property_map = [];

    $property_map[self::REQUESTS_COUNT] =
        parent::createStringProperty($this->count, true);
    return $property_map;
  }


  /**
   * Fetch a feed object given its feed URL.  If get a cache miss, fetch from the Datastore.
   * @param $feed_url URL of the feed.
   */
  public static function get($feed_url) {
    $mc = new Memcache();
    $key = self::getCacheKey($feed_url);
    $response = $mc->get($key);
    if ($response) {
      return [$response];
    }

    $query = parent::createQuery(self::TABLE_NAME);
    $feed_url_filter = parent::createStringFilter(self::REQUESTS_COUNT,
        $feed_url);
    $filter = parent::createCompositeFilter([$feed_url_filter]);
    $query->setFilter($filter);
    $results = parent::executeQuery($query);
    $extracted = self::extractQueryResults($results);
    return $extracted;
  }

  /**
   * This method will be called after a Datastore put.
   */
  protected function onItemWrite() {
    $mc = new Memcache();
    try {
      $key = self::getCacheKey($this->count);
      $mc->add($key, $this, 0, 120);
    }
    catch (Google_Cache_Exception $ex) {
      syslog(LOG_WARNING, "in onItemWrite: memcache exception");
    }
  }

  /**
  * This method will be called prior to a datastore delete
  */
  protected function beforeItemDelete() {
    $mc = new Memcache();
    $key = self::getCacheKey($this->count);
    $mc->delete($key);
  }

  /**
   * Extract the results of a Datastore query into ParselyModel objects
   * @param $results Datastore query results
   */
  protected static function extractQueryResults($results) {
    $query_results = [];
    foreach($results as $result) {
      $id = @$result['entity']['key']['path'][0]['id'];
      $key_name = @$result['entity']['key']['path'][0]['name'];
      $props = $result['entity']['properties'];
      $url = $props[self::REQUESTS_COUNT]->getStringValue();

      $feed_model = new ParselyModel($url);
      $feed_model->setKeyId($id);
      $feed_model->setKeyName($key_name);
      // Cache this read feed.
      $feed_model->onItemWrite();

      $query_results[] = $feed_model;
    }
    return $query_results;
  }

  private static function getCacheKey($feed_url) {
    return sprintf("%s_%s", self::TABLE_NAME, sha1($feed_url));
  }
}
