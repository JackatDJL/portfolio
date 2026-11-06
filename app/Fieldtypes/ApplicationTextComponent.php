<?php

namespace App\Fieldtypes;

use App\Support\ApplicationData;
use Illuminate\Validation\Rule;
use Statamic\Facades\User;
use Statamic\Fieldtypes\Select;

/** Native Select in a Bard set, populated from encrypted application defaults. */
class ApplicationTextComponent extends Select
{
    protected $component = 'select';

    protected function getOptions(): array
    {
        if (! User::current()?->isSuper()) {
            return [];
        }

        return collect(ApplicationData::textComponents())
            ->map(fn ($component) => ['value' => $component['key'], 'label' => $component['label']])
            ->values()->all();
    }

    public function rules(): array
    {
        return [Rule::in(array_column(ApplicationData::textComponents(), 'key'))];
    }
}
