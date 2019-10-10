<?php

namespace plansequenz\scoutbase\variables;

use plansequenz\scoutbase\Scoutbase;

class ScoutbaseVariable
{
    public function firebaseDatabaseUrl(): string
    {
        return Scoutbase::$plugin->getSettings()->getDatabaseUrl();
    }

    public function getPluginName()
    {
        return Scoutbase::$plugin->getSettings()->pluginName;
    }
}
