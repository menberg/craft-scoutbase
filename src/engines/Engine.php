<?php

namespace plansequenz\scoutbase\engines;

use Algolia\AlgoliaSearch\SearchClient;
use plansequenz\scoutbase\IndexSettings;
use plansequenz\scoutbase\ScoutbaseIndex;

abstract class Engine
{
    /** @var ScoutbaseIndex */
    public $scoutbaseIndex;

    abstract public function __construct(ScoutbaseIndex $scoutbaseIndex, SearchClient $algolia);

    abstract public function update($models);

    abstract public function delete($models);

    abstract public function flush();

    abstract public function updateSettings(IndexSettings $indexSettings);

    abstract public function getSettings(): array;

    abstract public function getTotalRecords(): int;

    public function splitObjects(array $objects): array
    {
        $objectsToSave = [];
        $objectsToDelete = [];

        foreach ($objects as $object) {
            $splittedObjects = $this->splitObject($object);

            if (count($splittedObjects) <= 1) {
                $object['distinctID'] = $object['objectID'];
                $objectsToSave[] = $object;
                continue;
            }

            foreach ($splittedObjects as $part => $splittedObject) {
                $splittedObject['distinctID'] = $splittedObject['objectID'];
                $splittedObject['objectID'] = "{$splittedObject['objectID']}_{$part}";
                $objectsToSave[] = $splittedObject;
            }

            $objectsToDelete[] = $object;
        }

        return [
            'save'   => $objectsToSave,
            'delete' => $objectsToDelete,
        ];
    }

    public function splitObject(array $data): array
    {
        $pieces = [];
        foreach ($this->scoutbaseIndex->splitElementsOn as $splitElementOn) {
            $pieces[$splitElementOn] = [];
            if (isset($data[$splitElementOn]) && is_array($data[$splitElementOn])) {
                $pieces[$splitElementOn] = $data[$splitElementOn];
            }
        }

        $objects = [[]];
        foreach (array_filter($pieces) as $splittedBy => $values) {
            $temp = [];
            foreach ($objects as $object) {
                foreach ($values as $value) {
                    $temp[] = array_merge($object, [$splittedBy => $value]);
                }
            }
            $objects = $temp;
        }

        return array_map(function ($object) use ($data) {
            return array_merge($data, $object);
        }, $objects);
    }
}
