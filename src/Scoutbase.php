<?php

namespace plansequenz\scoutbase;

use Google\Cloud\Firestore\FirestoreClient;
use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\events\DefineBehaviorsEvent;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Elements;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use Exception;
use plansequenz\scoutbase\behaviors\SearchableBehavior;
use plansequenz\scoutbase\models\Settings;
use plansequenz\scoutbase\utilities\ScoutbaseUtility;
use plansequenz\scoutbase\variables\ScoutbaseVariable;
use yii\base\Event;

class Scoutbase extends Plugin
{
    const EDITION_STANDARD = 'standard';
    const EDITION_PRO = 'pro';

    public static function editions(): array
    {
        return [
            self::EDITION_STANDARD,
            self::EDITION_PRO,
        ];
    }

    /** @var \plansequenz\scoutbase\Scoutbase */
    public static $plugin;

    public $hasCpSettings = true;

    /** @var \Tightenco\Collect\Support\Collection */
    private $beforeDeleteRelated;

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        Craft::$container->setSingleton(FirestoreClient::class, function () {
            $config = [
                'projectId' => self::$plugin->getSettings()->getProjectId(),
                'keyFilePath' => self::$plugin->getSettings()->getApplicationCredentials(),
            ];
            return new FirestoreClient($config);
        });

        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            $this->controllerNamespace = 'plansequenz\scoutbase\console\controllers\scoutbase';
        }

        $this->validateConfig();
        $this->registerBehaviors();
        $this->registerVariables();
        $this->registerEventHandlers();

        if (self::getInstance()->is(self::EDITION_PRO)) {
            $this->registerUtility();
        }
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    public function getSettings(): Settings
    {
        return parent::getSettings();
    }

    /** @codeCoverageIgnore */
    protected function settingsHtml()
    {
        $overrides = Craft::$app->getConfig()->getConfigFromFile(strtolower($this->handle));

        return Craft::$app->getView()->renderTemplate('scoutbase/settings', [
            'settings'  => $this->getSettings(),
            'overrides' => array_keys($overrides),
        ]);
    }

    private function registerUtility()
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ScoutbaseUtility::class;
            }
        );
    }

    private function registerBehaviors()
    {
        // Register the behavior on the Element class
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $event->behaviors['searchable'] = SearchableBehavior::class;
            }
        );
    }

    private function registerVariables()
    {
        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('scoutbase', ScoutbaseVariable::class);
            }
        );
    }

    private function validateConfig()
    {
        $indices = $this->getSettings()->getIndices();

        if ($indices->unique('indexName')->count() !== $indices->count()) {
            throw new Exception('Index names must be unique in the Scoutbase config.');
        }
    }

    private function registerEventHandlers()
    {
        $events = [
            [Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT],
            [Elements::class, Elements::EVENT_AFTER_RESTORE_ELEMENT],
            [Elements::class, Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI],
        ];

        foreach ($events as $event) {
            Event::on($event[0], $event[1],
                function (ElementEvent $event) {
                    /** @var SearchableBehavior $element */
                    $element = $event->element;
                    $element->searchable();
                }
            );
        }

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_DELETE_ELEMENT,
            function (ElementEvent $event) {
                /** @var SearchableBehavior $element */
                $element = $event->element;
                $this->beforeDeleteRelated = $element->getRelatedElements();
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function (ElementEvent $event) {
                /** @var SearchableBehavior $element */
                $element = $event->element;
                $element->unsearchable();

                if ($this->beforeDeleteRelated) {
                    $this->beforeDeleteRelated->each(function (Element $relatedElement) {
                        /* @var SearchableBehavior $relatedElement */
                        $relatedElement->searchable(false);
                    });
                }
            }
        );
    }
}
