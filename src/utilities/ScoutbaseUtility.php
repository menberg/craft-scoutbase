<?php

namespace plansequenz\scoutbase\utilities;

use Craft;
use craft\base\Utility;
use plansequenz\scoutbase\engines\Engine;
use plansequenz\scoutbase\Scoutbase;

class ScoutbaseUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('scoutbase', 'Scoutbase Indices');
    }

    public static function id(): string
    {
        return 'scoutbase-indices';
    }

    public static function iconPath(): string
    {
        return Craft::getAlias('@app/icons/magnifying-glass.svg');
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();

        $engines = Scoutbase::$plugin->getSettings()->getEngines();

        $stats = $engines->map(function (Engine $engine) {
            return [
                'name'        => $engine->scoutbaseIndex->indexName,
                'elementType' => $engine->scoutbaseIndex->elementType,
                'site'        => Craft::$app->getSites()->getSiteById($engine->scoutbaseIndex->criteria->siteId),
                'indexed'     => $engine->getTotalRecords(),
                'elements'    => $engine->scoutbaseIndex->criteria->count(),
            ];
        });

        return $view->renderTemplate('scoutbase/utility', [
            'stats' => $stats,
        ]);
    }
}
