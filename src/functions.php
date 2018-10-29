<?php

namespace HttpClient;

function isJson($string)
{
    if (in_array($string[0], ['[', '{'])) {
        json_decode($string);

        return (json_last_error() == JSON_ERROR_NONE);
    } else {
        return false;
    }
}

