<?php

namespace Clapp\SzamlazzhuClient;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator as BaseValidator;

class Validator extends BaseValidator
{

    public static function make(array $data, array $rules, array $messages = [], array $customAttributes = [])
    {
        return new static(new Translator(new ArrayLoader(), 'en'), $data, $rules, $messages, $customAttributes);
    }
}
