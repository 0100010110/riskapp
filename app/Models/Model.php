<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel
{
    protected $maps = [];

    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->maps)) {
            $key = $this->maps[$key];
        }

        if (str_contains($key, '.')) {
            $target = $this;
            $segments = explode('.', $key);

            foreach ($segments as $segment) {
                $target = $target->$segment;
            }

            return $target;
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (array_key_exists($key, $this->maps)) {
            $key = $this->maps[$key];
        }

        return parent::setAttribute($key, $value);
    }
}
