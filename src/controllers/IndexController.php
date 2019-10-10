<?php

namespace plansequenz\scoutbase\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use plansequenz\scoutbase\engines\Engine;
use plansequenz\scoutbase\jobs\ImportIndex;
use plansequenz\scoutbase\Scoutbase;
use plansequenz\scoutbase\utilities\ScoutbaseUtility;

class IndexController extends Controller
{
    public function actionFlush()
    {
        $this->requirePostRequest();

        $engine = $this->getEngine();
        $engine->flush();

        Craft::$app->getSession()->setNotice("Flushed index {$engine->scoutbaseIndex->indexName}");

        return $this->redirect(UrlHelper::url('utilities/'.ScoutbaseUtility::id()));
    }

    public function actionImport()
    {
        $this->requirePostRequest();

        $engine = $this->getEngine();

        if (Scout::$plugin->getSettings()->queue) {
            Craft::$app->getQueue()->push(new ImportIndex([
                'indexName' => $engine->scoutbaseIndex->indexName,
            ]));

            Craft::$app->getSession()->setNotice("Queued job to update element(s) in {$engine->scoutbaseIndex->indexName}");

            return $this->redirect(UrlHelper::url('utilities/'.ScoutbaseUtility::id()));
        }

        $elementsCount = $engine->scoutbaseIndex->criteria->count();
        $batch = $engine->scoutbaseIndex->criteria->batch(
            Scoutbase::$plugin->getSettings()->batch_size
        );

        foreach ($batch as $elements) {
            $engine->update($elements);
        }

        Craft::$app->getSession()->setNotice("Updated {$elementsCount} element(s) in {$engine->scoutbaseIndex->indexName}");

        return $this->redirect(UrlHelper::url('utilities/'.ScoutbaseUtility::id()));
    }

    public function actionRefresh()
    {
        $this->requirePostRequest();

        $this->actionFlush();
        $this->actionImport();

        return $this->redirect(UrlHelper::url('utilities/'.ScoutbaseUtility::id()));
    }

    private function getEngine(): Engine
    {
        $index = Craft::$app->getRequest()->getRequiredBodyParam('index');
        $engines = Scoutbase::$plugin->getSettings()->getEngines();

        /* @var \plansequenz\scoutbase\engines\Engine $engine */
        return $engines->first(function (Engine $engine) use ($index) {
            return $engine->scoutbaseIndex->indexName === $index;
        });
    }
}
