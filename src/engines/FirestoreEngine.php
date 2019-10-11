<?php

namespace plansequenz\scoutbase\engines;

use Google\Cloud\Firestore\FirestoreClient as Firestore;
use craft\base\Element;
use plansequenz\scoutbase\IndexSettings;
use plansequenz\scoutbase\ScoutbaseIndex;
use plansequenz\scoutbase\Scoutbase;
use Tightenco\Collect\Support\Arr;
use Tightenco\Collect\Support\Collection;

class FirestoreEngine extends Engine
{
    /** @var \Google\Cloud\Firestore\FirestoreClient */
    protected $firestore;

    /** @var \plansequenz\scoutbase\ScoutbaseIndex */
    public $scoutbaseIndex;

    public function __construct(ScoutbaseIndex $scoutbaseIndex, Firestore $firestore)
    {
        $this->scoutbaseIndex = $scoutbaseIndex;
        $this->firestore = $firestore;
    }

    /**
     * Update the given model in the index.
     *
     * @param array|Element $elements
     *
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
            $batch = $this->firestore->batch();
            $collection = $this->firestore->collection($this->scoutbaseIndex->indexName);
            foreach ($objects as $object) {
                $batch->set($collection->document($object['objectID']), $object);
            }
            $batch->commit();
        }
    }

    public function delete($elements)
    {
        $elements = new Collection(Arr::wrap($elements));

        $objectIds = $elements->map(function ($object) {
            if ($object instanceof Element) {
                return $object->id;
            }

            return $object['distinctID'] ?? $object['objectID'];
        })->unique()->values()->all();

        if (empty($objectIds)) {
            return;
        }

        $collection = $this->firestore->collection($this->scoutbaseIndex->indexName);

        $batch = $this->firestore->batch();
        foreach ($objectIds as $objectId) {
            $batch->delete($collection->document($objectId));
        }
        return $batch->commit();
    }

    public function flush()
    {
        $collection = $this->firestore->collection($this->scoutbaseIndex->indexName);
        $batchSize = Scoutbase::getInstance()->getSettings()->batch_size;
        $this->delete_collection($collection, $batchSize);
    }

    public function updateSettings(IndexSettings $indexSettings)
    {
        $index = $this->firestore->initIndex($this->scoutbaseIndex->indexName);
        $index->setSettings($indexSettings->settings);
    }

    public function getSettings(): array
    {
        $index = $this->firestore->initIndex($this->scoutbaseIndex->indexName);

        return $index->getSettings();
    }

    public function getTotalRecords(): int
    {
        /* $index = $this->firestore->initIndex($this->scoutbaseIndex->indexName);
        $response = $index->search('', [
            'attributesToRetrieve' => null,
        ]);

        return (int) $response['nbHits']; */
        return (int) 0;
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

    private function delete_collection($collectionReference, $batchSize)
    {
        $documents = $collectionReference->limit($batchSize)->documents();
        while (!$documents->isEmpty()) {
            foreach ($documents as $document) {
                // printf('Deleting document %s' . PHP_EOL, $document->id());
                $document->reference()->delete();
            }
            $documents = $collectionReference->limit($batchSize)->documents();
        }
    }
}
