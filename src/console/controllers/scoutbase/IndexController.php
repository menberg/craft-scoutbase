<?php

namespace plansequenz\scoutbase\console\controllers\scoutbase;

use Craft;
use craft\helpers\Console;
use plansequenz\scoutbase\console\controllers\BaseController;
use plansequenz\scoutbase\engines\Engine;
use plansequenz\scoutbase\Scoutbase;
use yii\console\ExitCode;

class IndexController extends BaseController
{
    public $defaultAction = 'refresh';

    /** @var bool */
    public $force = false;

    public function options($actionID)
    {
        return ['force'];
    }

    public function actionFlush($index = '')
    {
        if (
            $this->force === false
            && $this->confirm(Craft::t('scoutbase', 'Are you sure you want to flush Scoutbase?')) === false
        ) {
            return ExitCode::OK;
        }

        $engines = Scoutbase::$plugin->getSettings()->getEngines();
        $engines->filter(function (Engine $engine) use ($index) {
            return $index === '' || $engine->scoutbaseIndex->indexName === $index;
        })->each(function (Engine $engine) {
            $engine->flush();
            $this->stdout("Flushed index {$engine->scoutbaseIndex->indexName}\n", Console::FG_GREEN);
        });

        return ExitCode::OK;
    }

    public function actionImport($index = '')
    {
        $engines = Scoutbase::$plugin->getSettings()->getEngines();

        $engines->filter(function (Engine $engine) use ($index) {
            return $index === '' || $engine->scoutbaseIndex->indexName === $index;
        })->each(function (Engine $engine) {
            $totalElements = $engine->scoutbaseIndex->criteria->count();
            $elementsUpdated = 0;
            $batch = $engine->scoutbaseIndex->criteria->batch(
                Scoutbase::$plugin->getSettings()->batch_size
            );

            foreach ($batch as $elements) {
                $engine->update($elements);
                $elementsUpdated += count($elements);
                $this->stdout("Updated {$elementsUpdated}/{$totalElements} element(s) in {$engine->scoutbaseIndex->indexName}\n", Console::FG_GREEN);
            }
        });

        return ExitCode::OK;
    }

    public function actionRefresh($index = '')
    {
        $this->actionFlush($index);
        $this->actionImport($index);

        return ExitCode::OK;
    }
}
