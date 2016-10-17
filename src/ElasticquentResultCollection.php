<?php

namespace Elasticquent;

use Illuminate\Database\Eloquent\Collection;

class ElasticquentResultCollection extends Collection
{
    protected $took;
    protected $timed_out;
    protected $shards;
    protected $hits;
    protected $aggregations = null;

    /**
     * _construct.
     *
     * @param $results elasticsearch results
     * @param $instance
     * @return \Elasticquent\ElasticquentResultCollection
     */
    public function __construct(array $items = [], array $meta = [])
    {
        $this->items = $items;

        // Take our result data and map it
        // to some class properties.
        $this->took = array_get($meta, 'took');
        $this->timed_out = array_get($meta, 'timed_out');
        $this->shards = array_get($meta, '_shards');
        $this->hits = array_get($meta, 'hits');
        $this->aggregations = array_get($meta, 'aggregations', []);
    }

    /**
     * Total Hits.
     *
     * @return int
     */
    public function totalHits()
    {
        return (int) array_get($this->hits, 'total', 0);
    }

    /**
     * Max Score.
     *
     * @return float
     */
    public function maxScore()
    {
        return (float) array_get($this->hits, 'max_score', 0);
    }

    /**
     * Get Shards.
     *
     * @return array
     */
    public function getShards()
    {
        return $this->shards;
    }

    /**
     * Took.
     *
     * @return string
     */
    public function took()
    {
        return $this->took;
    }

    /**
     * Timed Out.
     *
     * @return bool
     */
    public function timedOut()
    {
        return (bool) $this->timed_out;
    }

    /**
     * Get Hits.
     *
     * @return array
     */
    public function getHits()
    {
        return $this->hits;
    }

    /**
     * Get aggregations.
     *
     * @return array
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }
}
