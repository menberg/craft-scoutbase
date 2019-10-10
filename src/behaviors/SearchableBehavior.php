<?php

namespace plansequenz\scoutbase\behaviors;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\MatrixBlock;
use craft\elements\Tag;
use craft\elements\User;
use craft\helpers\ElementHelper;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use plansequenz\scoutbase\engines\Engine;
use plansequenz\scoutbase\jobs\MakeSearchable;
use plansequenz\scoutbase\Scoutbase;
use plansequenz\scoutbase\ScoutbaseIndex;
use plansequenz\scoutbase\serializer\AlgoliaSerializer;
use Tightenco\Collect\Support\Arr;
use Tightenco\Collect\Support\Collection;
use yii\base\Behavior;

/**
 * @mixin Element
 *
 * @property Element $owner
 * @property int $id
 */
class SearchableBehavior extends Behavior
{
    public function validatesCriteria(ScoutbaseIndex $scoutbaseIndex): bool
    {
        $criteria = clone $scoutbaseIndex->criteria;

        return $criteria
            ->id($this->owner->id)
            ->exists();
    }

    public function getIndices(): Collection
    {
        return Scoutbase::$plugin
            ->getSettings()
            ->getIndices()
            ->filter(function (ScoutbaseIndex $scoutbaseIndex) {
                $siteIds = array_map(function ($siteId) {
                    return (int) $siteId;
                }, Arr::wrap($scoutbaseIndex->criteria->siteId));

                return $scoutbaseIndex->elementType === get_class($this->owner)
                    && ($scoutbaseIndex->criteria->siteId === '*'
                        || in_array((int) $this->owner->siteId, $siteIds));
            });
    }

    public function searchableUsing(): Collection
    {
        return $this->getIndices()->map(function (ScoutbaseIndex $scoutbaseIndex) {
            return Scoutbase::$plugin->getSettings()->getEngine($scoutbaseIndex);
        });
    }

    public function searchable(bool $propagate = true)
    {
        if (!$this->shouldBeSearchable()) {
            return;
        }

        $this->searchableUsing()->each(function (Engine $engine) {
            if (!$this->validatesCriteria($engine->scoutbaseIndex)) {
                return $engine->delete($this->owner);
            }

            if (Scoutbase::$plugin->getSettings()->queue) {
                return Craft::$app->getQueue()->push(
                    new MakeSearchable([
                        'id'        => $this->owner->id,
                        'siteId'    => $this->owner->siteId,
                        'indexName' => $engine->scoutbaseIndex->indexName,
                    ])
                );
            }

            return $engine->update($this->owner);
        });

        if ($propagate) {
            $this->getRelatedElements()->each(function (Element $relatedElement) {
                /* @var SearchableBehavior $relatedElement */
                $relatedElement->searchable(false);
            });
        }
    }

    public function unsearchable()
    {
        if (!Scoutbase::$plugin->getSettings()->sync) {
            return;
        }

        $this->searchableUsing()->each->delete($this->owner);
    }

    public function toSearchableArray(ScoutbaseIndex $scoutbaseIndex): array
    {
        return (new Manager())
            ->setSerializer(new AlgoliaSerializer())
            ->createData(new Item($this->owner, $scoutbaseIndex->getTransformer()))
            ->toArray();
    }

    public function getRelatedElements(): Collection
    {
        $assets = Asset::find()->relatedTo($this->owner)->site('*')->all();
        $categories = Category::find()->relatedTo($this->owner)->site('*')->all();
        $entries = Entry::find()->relatedTo($this->owner)->site('*')->all();
        $tags = Tag::find()->relatedTo($this->owner)->site('*')->all();
        $users = User::find()->relatedTo($this->owner)->site('*')->all();
        $globalSets = GlobalSet::find()->relatedTo($this->owner)->site('*')->all();
        $matrixBlocks = MatrixBlock::find()->relatedTo($this->owner)->site('*')->all();

        $products = [];
        $variants = [];
        // @codeCoverageIgnoreStart
        if (class_exists(Product::class)) {
            $products = Product::find()->relatedTo($this->owner)->site('*')->all();
            $variants = Variant::find()->relatedTo($this->owner)->site('*')->all();
        }
        // @codeCoverageIgnoreEnd

        return new Collection(array_merge(
            $assets,
            $categories,
            $entries,
            $tags,
            $users,
            $globalSets,
            $matrixBlocks,
            $products,
            $variants
        ));
    }

    public function shouldBeSearchable(): bool
    {
        if (!Scoutbase::$plugin->getSettings()->sync) {
            return false;
        }

        if ($this->owner->propagating) {
            return false;
        }

        if (ElementHelper::isDraftOrRevision($this->owner)) {
            return false;
        }

        return true;
    }
}
