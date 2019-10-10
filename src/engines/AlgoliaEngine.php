<?php

namespace plansequenz\scoutbase\engines;

use Algolia\AlgoliaSearch\SearchClient as Algolia;
use craft\base\Element;
use plansequenz\scoutbase\IndexSettings;
use plansequenz\scoutbase\ScoutbaseIndex;
use Tightenco\Collect\Support\Arr;
use Tightenco\Collect\Support\Collection;

class AlgoliaEngine extends Engine
{
    /** @var \Algolia\AlgoliaSearch\SearchClient */
    protected $algolia;

    /** @var \plansequenz\scoutbase\ScoutbaseIndex */
    public $scoutbaseIndex;

    public function __construct(ScoutbaseIndex $scoutbaseIndex, Algolia $algolia)
    {
        $this->scoutbaseIndex = $scoutbaseIndex;
        $this->algolia = $algolia;
    }

    /**
     * Update the given model in the index.
     *
     * @param array|Element $elements
     *
     * @throws \Algolia\AlgoliaSearch\Exceptions\AlgoliaException
     */
    public function update($elements)
    {
        $elements = new Collection(Arr::wrap($elements));

        $elements = $elements->filter(function (Element $element) {
            return get_class($element) === $this->scoutbaseIndex->elementType;
        });

        if ($elements->isEmpty()) {
            return;
        }
        $objects = $this->transformElements($elements);

        if (!empty($objects)) {
            $index = $this->algolia->initIndex($this->scoutbaseIndex->indexName);
            $index->saveObjects($objects);
        }
    }

    public function delete($elements)
    {
        $elements = new Collection(Arr::wrap($elements));

        $index = $this->algolia->initIndex($this->scoutbaseIndex->indexName);

        $objectIds = $elements->map(function ($object) {
            if ($object instanceof Element) {
                return $object->id;
            }

            return $object['distinctID'] ?? $object['objectID'];
        })->unique()->values()->all();

        if (empty($objectIds)) {
            return;
        }

        if (empty($this->scoutbaseIndex->splitElementsOn)) {
            return $index->deleteObjects($objectIds);
        }

        return $index->deleteBy([
            'filters' => 'distinctID:'.implode(' OR distinctID:', $objectIds),
        ]);
    }

    public function flush()
    {
        $index = $this->algolia->initIndex($this->scoutbaseIndex->indexName);
        $index->clearObjects();
    }

    public function updateSettings(IndexSettings $indexSettings)
    {
        $index = $this->algolia->initIndex($this->scoutbaseIndex->indexName);
        $index->setSettings($indexSettings->settings);
    }

    public function getSettings(): array
    {
        $index = $this->algolia->initIndex($this->scoutbaseIndex->indexName);

        return $index->getSettings();
    }

    public function getTotalRecords(): int
    {
        $index = $this->algolia->initIndex($this->scoutbaseIndex->indexName);
        $response = $index->search('', [
            'attributesToRetrieve' => null,
        ]);

        return (int) $response['nbHits'];
    }

    private function transformElements(Collection $elements): array
    {
        $objects = $elements->map(function (Element $element) {
            /** @var \plansequenz\scoutbase\behaviors\SearchableBehavior $element */
            if (empty($searchableData = $element->toSearchableArray($this->scoutbaseIndex))) {
                return;
            }

            return array_merge(
                ['objectID' => $element->id],
                $searchableData
            );
        })->filter()->values()->all();

        if (empty($this->scoutbaseIndex->splitElementsOn)) {
            return $objects;
        }

        $result = $this->splitObjects($objects);

        $this->delete($result['delete']);

        $objects = $result['save'];

        return $objects;
    }
}
