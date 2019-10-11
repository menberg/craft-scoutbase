<?php

namespace plansequenz\scoutbase\models;

use Craft;
use craft\base\Model;
use Exception;
use plansequenz\scoutbase\engines\FirestoreEngine;
use plansequenz\scoutbase\engines\Engine;
use plansequenz\scoutbase\ScoutbaseIndex;
use Tightenco\Collect\Support\Collection;

class Settings extends Model
{
    /** @var string */
    public $pluginName = 'Scoutbase';

    /** @var bool */
    public $sync = true;

    /** @var bool */
    public $queue = true;

    /** @var string */
    public $engine = FirestoreEngine::class;

    /** @var ScoutbaseIndex[] */
    public $indices = [];

    /* @var string */
    public $application_credentials = '';

    /* @var string */
    public $project_id = '';

    /* @var int */
    public $connect_timeout = 1;

    /* @var int */
    public $batch_size = 1000;

    public function rules()
    {
        return [
            [['connect_timeout', 'batch_size'], 'integer'],
            [['sync', 'queue'], 'boolean'],
            [['application_credentials', 'project_id'], 'string'],
            [['application_credentials', 'project_id'], 'required'],
        ];
    }

    public function getIndices(): Collection
    {
        return new Collection($this->indices);
    }

    public function getEngines(): Collection
    {
        return $this->getIndices()->map(function (ScoutbaseIndex $scoutbaseIndex) {
            return $this->getEngine($scoutbaseIndex);
        });
    }

    public function getEngine(ScoutbaseIndex $scoutbaseIndex): Engine
    {
        $engine = Craft::$container->get($this->engine, [$scoutbaseIndex]);

        if (!$engine instanceof Engine) {
            throw new Exception("Invalid engine {$this->engine}, must implement ".Engine::class);
        }

        return $engine;
    }

    public function getApplicationCredentials(): string
    {
        return Craft::parseEnv($this->application_credentials);
    }

    public function getProjectId(): string
    {
        return Craft::parseEnv($this->project_id);
    }
}
