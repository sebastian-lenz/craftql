<?php

namespace markhuot\CraftQL\FieldBehaviors;

use craft\base\Element;
use craft\elements\Entry;
use markhuot\CraftQL\Behaviors\FieldBehavior;
use markhuot\CraftQL\Builders\Field;
use Craft;

class EntryMutationArguments extends FieldBehavior {

    /**
     * @var Field the owner of this behavior
     */
    public $owner;

    function initEntryMutationArguments() {
        $this->owner->addIntArgument('id');
        $this->owner->addIntArgument('authorId');
        $this->owner->addStringArgument('title');

        $fieldLayoutId = $this->owner->getType()->getContext()->fieldLayoutId;
        $this->owner->addArgumentsByLayoutId($fieldLayoutId);

        $this->owner->resolve(function ($root, $args) {
            if (!empty($args['id'])) {
                $criteria = Entry::find();
                $criteria->id($args['id']);
                $entry = $criteria->one();
                if (!$entry) {
                    throw new \GraphQL\Error\UserError('Could not find an entry with id '.$args['id']);
                }
            }
            else {
                $entry = new Entry();
                $entry->sectionId = $this->owner->getType()->getContext()->section->id;
                $entry->typeId = $this->owner->getType()->getContext()->id;
                $entry->fieldLayoutId = $this->owner->getType()->getContext()->fieldLayoutId;

                if (empty($args['title'])) {
                    throw new \GraphQL\Error\UserError('You must set a title when upserting a new entry.');
                }
            }

            if (isset($args['authorId'])) {
                $entry->authorId = $args['authorId'];
            }
            else if (empty($args['authorId'])) {
                $entry->authorId = $this->owner->getRequest()->token()->user->id;
            }

            if (isset($args['title'])) {
                $entry->title = $args['title'];
            }

            $fields = $args;
            unset($fields['id']);
            unset($fields['title']);
            unset($fields['sectionId']);
            unset($fields['typeId']);
            unset($fields['authorId']);

            $fieldService = \Yii::$container->get('craftQLFieldService');

            foreach ($fields as $handle => &$value) {
                $callback = $this->owner->getArgument($handle)->getOnSave();
                if ($callback) {
                    $value = $callback($value);
                }
            }

            $entry->setFieldValues($fields);

            $entry->setScenario(Element::SCENARIO_LIVE);

            if (!Craft::$app->elements->saveElement($entry)) {
                $errorStrings = [];

                foreach ($entry->errors as $fieldName => $errors) {
                    $errorStrings = array_merge($errorStrings, $errors);
                }
                throw new \GraphQL\Error\UserError('Validation failed.'."\n\n- ".implode("\n-", $errorStrings));
            }

            return $entry;
        });
    }

}