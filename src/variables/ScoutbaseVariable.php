<?php

namespace plansequenz\scoutbase\variables;

use plansequenz\scoutbase\Scoutbase;

class ScoutbaseVariable
{
    public function firebaseProjectId(): string
    {
        return Scoutbase::$plugin->getSettings()->getProjectId();
    }

    public function getPluginName()
    {
        return Scoutbase::$plugin->getSettings()->pluginName;
    }
}
