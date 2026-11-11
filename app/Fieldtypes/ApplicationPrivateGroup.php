<?php

namespace App\Fieldtypes;

use App\Support\ApplicationData;
use Statamic\Facades\User;
use Statamic\Fieldtypes\Group;

/** Native Group editor with the same encrypted storage contract as the former Grid. */
class ApplicationPrivateGroup extends Group
{
    protected $component = 'group';

    public function process($value)
    {
        // Existing encrypted application fields used a one-row Grid array. Accept
        // that shape from old entries and normalize new saves to the same format.
        if (is_array($value) && array_is_list($value)) {
            $value = $value[0] ?? [];
        }

        return ApplicationData::encrypt([parent::process($value ?? [])]);
    }

    public function preProcess($value)
    {
        $group = is_array($value) ? $value : ApplicationData::groupValue($value);

        return parent::preProcess($group);
    }

    public function preProcessValidatable($value)
    {
        $group = is_array($value) ? $value : ApplicationData::groupValue($value);

        return parent::preProcessValidatable($group);
    }

    public function preProcessIndex($value)
    {
        if ($this->field()->handle() === 'recipient' && User::current()?->isSuper()) {
            return ApplicationData::groupValue($value)['organisation'] ?? 'Geschützt';
        }

        return 'Geschützt';
    }

    public function shallowAugment($value)
    {
        return null;
    }

    public function toQueryableValue($value)
    {
        return null;
    }

    public function augment($value)
    {
        return null;
    }
}
