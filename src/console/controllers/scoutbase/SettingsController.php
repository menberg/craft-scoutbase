<?php

namespace plansequenz\scoutbase\console\controllers\scoutbase;

use craft\helpers\Console;
use plansequenz\scoutbase\console\controllers\BaseController;
use plansequenz\scoutbase\engines\Engine;
use plansequenz\scoutbase\Scoutbase;
use yii\console\ExitCode;
use yii\helpers\VarDumper;

class SettingsController extends BaseController
{
    public $defaultAction = 'update';

    public function actionUpdate($index = '')
    {
        $engines = Scoutbase::$plugin->getSettings()->getEngines();
        $engines->filter(function (Engine $engine) use ($index) {
            return $index === '' || $engine->scoutbaseIndex->indexName === $index;
        })->each(function (Engine $engine) {
            $engine->updateSettings($engine->scoutbaseIndex->indexSettings);
            $this->stdout("Updated index settings for {$engine->scoutbaseIndex->indexName}\n", Console::FG_GREEN);
        });

        return ExitCode::OK;
    }

    public function actionDump($index = '')
    {
        $dump = [];

        $engines = Scoutbase::$plugin->getSettings()->getEngines();
        $engines->filter(function (Engine $engine) use ($index) {
            return $index === '' || $engine->scoutbaseIndex->indexName === $index;
        })->each(function (Engine $engine) use (&$dump) {
            $dump[$engine->scoutbaseIndex->indexName] = $engine->getSettings();
        });

        $this->stdout(VarDumper::dumpAsString($dump));

        return ExitCode::OK;
    }
}
