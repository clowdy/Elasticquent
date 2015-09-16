<?php

namespace Elasticquent;

use Elasticquent\ElasticquentResultCollection as ResultCollection;
use Elasticsearch\Client as Elasticsearch;
use Elasticquent\Exceptions\DocumentMissingException;

/**
 * Elasticquent Trait.
 *
 * Functionality extensions for Elequent that
 * makes working with Elasticsearch easier.
 */
trait ElasticquentTrait
{
    /**
     * Uses Timestamps In Index.
     *
     * @var bool
     */
    protected $usesTimestampsInIndex = true;

    /**
     * Is ES Document.
     *
     * Set to true when our model is
     * populated by a
     *
     * @var bool
     */
    protected $isDocument = false;

    /**
     * Document Score.
     *
     * Hit score when using data
     * from Elasticsearch results.
     *
     * @var null|int
     */
    protected $documentScore = null;

    /**
     * Document Version.
     *
     * Elasticsearch document version.
     *
     * @var null|int
     */
    protected $documentVersion = null;

    /**
     * Get ElasticSearch Client.
     *
     * @return \Elasticsearch\Client
     */
    public function getElasticSearchClient()
    {
        $config = config('elasticquent.config', []);

        return new Elasticsearch($config);
    }

    /**
     * New Collection.
     *
     * @param  array      $models
     * @return Collection
     */
    public function newCollection(array $models = [])
    {
        return new ElasticquentCollection($models);
    }

    /**
     * Get Index Name.
     *
     * @return string
     */
    public function getIndexName()
    {
        // The first thing we check is if there
        // is an elasticquery config file and if there is a
        // default index.
        // Otherwise we will just go with 'default'
        return config('elasticquent.default_index', 'default');
    }

    /**
     * Get Type Name.
     *
     * @return string
     */
    public function getTypeName()
    {
        return $this->getTable();
    }

    /**
     * Uses Timestamps In Index.
     *
     * @return void
     */
    public function usesTimestampsInIndex()
    {
        return $this->usesTimestampsInIndex;
    }

    /**
     * Use Timestamps In Index.
     *
     * @return void
     */
    public function useTimestampsInIndex()
    {
        $this->usesTimestampsInIndex = true;
    }

    /**
     * Don't Use Timestamps In Index.
     *
     * @return void
     */
    public function dontUseTimestampsInIndex()
    {
        $this->usesTimestampsInIndex = false;
    }

    /**
     * Get Index relationships to eager load.
     *
     * @return array
     */
    public function getIndexRelations()
    {
        return ($this->indexRelations) ?: [];
    }

    /**
     * Set Index relationships to eager load.
     *
     * @param array $relations
     * @internal param array $indexRelations
     */
    public function setIndexRelations(array $relations)
    {
        $this->indexRelations = $relations;
    }

    /**
     * Get query scopes for this model.
     *
     * @return array
     */
    public function getIndexQueryScopes()
    {
        return ($this->indexQueryScopes) ?: [];
    }

    /**
     * Set query scopes for this model.
     *
     * @param array $scopes
     * @internal param array $indexQueryScopes
     */
    public function setIndexQueryScopes(array $scopes)
    {
        $this->indexQueryScopes = $scopes;
    }

    /**
     * Get Mapping Properties.
     *
     * @return array
     */
    public function getMappingProperties()
    {
        return ($this->mappingProperties) ?: [];
    }

    /**
     * Set Mapping Properties.
     *
     * @param array $mapping
     * @internal param array $mappingProperties
     */
    public function setMappingProperties(array $mapping)
    {
        $this->mappingProperties = $mapping;
    }

    /**
     * Is Elasticsearch Document.
     *
     * Is the data in this module sourced
     * from an Elasticsearch document source?
     *
     * @return bool
     */
    public function isDocument()
    {
        return $this->isDocument;
    }

    /**
     * Get Document Score.
     *
     * @return null|float
     */
    public function documentScore()
    {
        return $this->documentScore;
    }

    /**
     * Document Version.
     *
     * @return null|int
     */
    public function documentVersion()
    {
        return $this->documentVersion;
    }

    /**
     * Get Index Document Data.
     *
     * Get the data that Elasticsearch will
     * index for this particular document.
     *
     * @return array
     */
    public function getIndexDocumentData()
    {
        return $this->toArray();
    }

    /**
     * Index Documents
     * Index all documents in an Eloquent model.
     * Optional chunking for large data sets.
     *
     * @param int $chunk
     *
     * @return array
     */
    public static function addAllToIndex($chunk = 0)
    {
        $query = self::getQuery();

        if ($chunk) {
            return $query->chunk($chunk, function ($data) {
                $data->addToIndex();
            });
        }

        return $query->get()->addToIndex();
    }

    /**
     * Re-Index All Content
     * Optional chunking for large data sets.
     *
     * @param int $chunk
     *
     * @return array
     */
    public static function reindex($chunk = 0)
    {
        $query = self::getQuery();

        if ($chunk) {
            return $query->chunk($chunk, function ($data) {
                $data->reindex();
            });
        }

        return $query->get()->reindex();
    }

    /**
     * Get new query builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function getQuery()
    {
        $instance = new static();

        $query = $instance->newQuery()->with($instance->getIndexRelations());

        foreach ($instance->getQueryScopes as $scope) {
            $query->$scope();
        }

        return $query;
    }

    /**
     * Build your own search.
     *
     * @param  array            $params
     * @param  int              $limit
     * @param  int              $offset
     * @return ResultCollection
     */
    public static function searchCustom($params = [], $limit = null, $offset = null)
    {
        $instance = new static();

        $basicParams = $instance->getBasicEsParams(true, true, true, $limit, $offset);

        $params = array_merge($basicParams, $params);

        $result = $instance->getElasticSearchClient()->search($params);

        return new ResultCollection($instance->hitsToItems($result), $result);
    }

    /**
     * Search By Query.
     *
     * Search with a query array
     *
     * @param  array            $query
     * @param  array            $aggregations
     * @param  array            $sourceFields
     * @param  int              $limit
     * @param  int              $offset
     * @return ResultCollection
     */
    public static function searchByQuery($query = null, $aggregations = null, $sourceFields = null, $limit = null, $offset = null)
    {
        $instance = new static();

        $params = $instance->getBasicEsParams(true, true, true, $limit, $offset);

        if ($sourceFields) {
            $params['body']['_source']['include'] = $sourceFields;
        }

        if ($query) {
            $params['body']['query'] = $query;
        }

        if ($aggregations) {
            $params['body']['aggs'] = $aggregations;
        }

        $result = $instance->getElasticSearchClient()->search($params);

        return new ResultCollection($instance->hitsToItems($result), $result);
    }

    /**
     * Search.
     *
     * Simple search using a match _all query
     *
     * @param  string           $term
     * @param  int              $limit
     * @param  int              $offset
     * @return ResultCollection
     */
    public static function search($term = null, $limit = null, $offset = null)
    {
        $instance = new static();

        $params = $instance->getBasicEsParams(true, false, false, $limit, $offset);

        $params['body']['query']['match']['_all'] = $term;

        $result = $instance->getElasticSearchClient()->search($params);

        return new ResultCollection($instance->hitsToItems($result), $result);
    }

    /**
     * Add to Search Index.
     *
     * @throws Exception
     * @return array
     */
    public function addToIndex()
    {
        if (!$this->exists) {
            throw new DocumentMissingException('Document does not exist.');
        }

        $params = $this->getBasicEsParams();

        // Get our document body data.
        $params['body'] = $this->getIndexDocumentData();

        // The id for the document must always mirror the
        // key for this model, even if it is set to something
        // other than an auto-incrementing value. That way we
        // can do things like remove the document from
        // the index, or get the document from the index.
        $params['id'] = $this->getKey();

        return $this->getElasticSearchClient()->index($params);
    }

    /**
     * Remove From Search Index.
     *
     * @return array
     */
    public function removeFromIndex()
    {
        return $this->getElasticSearchClient()->delete($this->getBasicEsParams());
    }

    /**
     * Get Search Document.
     *
     * Retrieve an ElasticSearch document
     * for this enty.
     *
     * @return array
     */
    public function getIndexedDocument()
    {
        return $this->getElasticSearchClient()->get($this->getBasicEsParams());
    }

    /**
     * Get Basic Elasticsearch Params.
     *
     * Most Elasticsearch API calls need the index and
     * type passed in a parameter array.
     *
     * @param bool $getIdIfPossible
     * @param bool $getSourceIfPossible
     * @param bool $getTimestampIfPossible
     * @param int  $limit
     * @param int  $offset
     *
     * @return array
     */
    public function getBasicEsParams($getIdIfPossible = true, $getSourceIfPossible = false, $getTimestampIfPossible = false, $limit = null, $offset = null)
    {
        $params = [
            'index'     => $this->getIndexName(),
            'type'      => $this->getTypeName(),
        ];

        if ($getIdIfPossible and $this->getKey()) {
            $params['id'] = $this->getKey();
        }

        $fieldsParam = [];

        if ($getSourceIfPossible) {
            array_push($fieldsParam, '_source');
        }

        if ($getTimestampIfPossible) {
            array_push($fieldsParam, '_timestamp');
        }

        if ($fieldsParam) {
            $params['fields'] = implode(',', $fieldsParam);
        }

        if (is_numeric($limit)) {
            $params['size'] = $limit;
        }

        if (is_numeric($offset)) {
            $params['from'] = $offset;
        }

        return $params;
    }

    /**
     * Mapping Exists.
     *
     * @return bool
     */
    public static function mappingExists()
    {
        $instance = new static();

        $mapping = $instance->getMapping();

        return (empty($mapping)) ? false : true;
    }

    /**
     * Get Mapping.
     *
     * @return void
     */
    public static function getMapping()
    {
        $instance = new static();

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->getMapping($params);
    }

    /**
     * Put Mapping.
     *
     * @param  bool  $ignoreConflicts
     * @return array
     */
    public static function putMapping($ignoreConflicts = false)
    {
        $instance = new static();

        $mapping = $instance->getBasicEsParams();

        $params = [
            '_source'       => ['enabled' => true],
            'properties'    => $instance->getMappingProperties(),
        ];

        $mapping['body'][$instance->getTypeName()] = $params;
        $mapping['ignore_conflicts'] = $ignoreConflicts;

        return $instance->getElasticSearchClient()->indices()->putMapping($mapping);
    }

    /**
     * Delete Mapping.
     *
     * @return array
     */
    public static function deleteMapping()
    {
        $instance = new static();

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->deleteMapping($params);
    }

    /**
     * Rebuild Mapping.
     *
     * This will delete and then re-add
     * the mapping for this model.
     *
     * @return array
     */
    public static function rebuildMapping()
    {
        $instance = new static();

        // If the mapping exists, let's delete it.
        if ($instance->mappingExists()) {
            $instance->deleteMapping();
        }

        // Don't need ignore conflicts because if we
        // just removed the mapping there shouldn't
        // be any conflicts.
        return $instance->putMapping();
    }

    /**
     * Create Index.
     *
     * @param  int   $shards
     * @param  int   $replicas
     * @return array
     */
    public static function createIndex($shards = null, $replicas = null)
    {
        $instance = new static();

        $index = ['index' => $instance->getIndexName()];

        if ($shards) {
            $index['body']['settings']['number_of_shards'] = $shards;
        }

        if ($replicas) {
            $index['body']['settings']['number_of_replicas'] = $replicas;
        }

        return $instance->getElasticSearchClient()->indices()->create($index);
    }

    /**
     * Delete Index.
     *
     * @return array
     */
    public static function deleteIndex()
    {
        $instance = new static();

        $index = ['index' => $instance->getIndexName()];

        return $instance->getElasticSearchClient()->indices()->delete($index);
    }

    /**
     * Index Exists.
     *
     * Does this index exist?
     *
     * @return bool
     */
    public static function indexExists()
    {
        $instance = new static();

        $params = ['index' => $instance->getIndexName()];

        return $instance->getElasticSearchClient()->indices()->exists($params);
    }

    /**
     * Type Exists.
     *
     * Does this type exist?
     *
     * @return bool
     */
    public static function typeExists()
    {
        $instance = new static();

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->exists($params);
    }

    /**
     * Optimize the elasticsearch index.
     *
     * @param  array $params
     * @return bool
     */
    public static function optimize(array $params = [])
    {
        $instance = new static();

        $basicParams = ['index' => $instance->getIndexName()];

        $params = array_merge($basicParams, $params);

        return $instance->getElasticSearchClient()->indices()->optimize($params);
    }

    /**
     * Hits To Items.
     *
     * @param  Eloquent model instance $instance
     * @return array
     */
    protected function hitsToItems(array $results)
    {
        $items = [];

        foreach (array_get($results, 'hits.hits') as $hit) {
            $items[] = $this->newFromHitBuilder($hit);
        }

        return $items;
    }

    /**
     * New From Hit Builder.
     *
     * Variation on newFromBuilder. Instead, takes
     *
     * @param  array  $hit
     * @return static
     */
    protected function newFromHitBuilder(array $hit = [])
    {
        $instance = $this->newInstance([], true);

        // Add fields to attributes
        if (isset($hit['fields'])) {
            foreach ($hit['fields'] as $key => $value) {
                $attributes[$key] = $value;
            }
        } else {
            $attributes = array_get($hit, '_source');
        }

        if (isset($hit['highlight'])) {
            foreach ($hit['highlight'] as $key => $value) {
                $attributes[$key] = $value;
            }
        }

        $instance->setRawAttributes((array) $attributes, true);

        // In addition to setting the attributes
        // from the index, we will set the score as well.
        $instance->documentScore = array_get($hit, '_score');

        // This is now a model created
        // from an Elasticsearch document.
        $instance->isDocument = true;

        // Set our document version
        $instance->documentVersion = array_get($hit, '_version');

        return $instance;
    }
}
