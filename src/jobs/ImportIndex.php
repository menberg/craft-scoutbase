<?php

namespace plansequenz\scoutbase\jobs;

use craft\queue\BaseJob;
use plansequenz\scoutbase\engines\Engine;
use plansequenz\scoutbase\Scoutbase;

class ImportIndex extends BaseJob
{
    /** @var string */
    public $indexName;

    public function execute($queue)
    {
        /** @var Engine $engine */
        $engine = Scoutbase::$plugin->getSettings()->getEngines()->first(function (Engine $engine) {
            return $engine->scoutbaseIndex->indexName === $this->indexName;
        });

        if (!$engine) {
            return;
        }

        $elementsCount = $engine->scoutbaseIndex->criteria->count();
        $elementsUpdated = 0;
        $batch = $engine->scoutbaseIndex->criteria->batch(
            Scoutbase::$plugin->getSettings()->batch_size
        );

        foreach ($batch as $elements) {
            $engine->update($elements);
            $elementsUpdated += count($elements);
            $this->setProgress($queue, $elementsUpdated / $elementsCount);
        }
    }

    protected function defaultDescription()
    {
        return sprintf(
            'Indexing element(s) in “%s”',
            $this->indexName
        );
    }
}
