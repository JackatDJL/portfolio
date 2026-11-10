<?php

namespace App\Fieldtypes;

use App\Support\ApplicationData;
use Statamic\Facades\User;
use Statamic\Fieldtypes\Grid;

/** Native Grid/Bard/relationship editing with encryption at the field boundary. */
class ApplicationPrivateGrid extends Grid
{
    protected $component = 'grid';

    public function process($value)
    {
        return ApplicationData::encrypt(parent::process($value ?? []));
    }

    public function preProcess($value)
    {
        return parent::preProcess(is_array($value) ? $value : ApplicationData::decrypt($value));
    }

    public function preProcessIndex($value)
    {
        if ($this->field()->handle() === 'recipient' && User::current()?->isSuper()) {
            return ApplicationData::decrypt($value)[0]['organisation'] ?? 'Geschützt';
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
