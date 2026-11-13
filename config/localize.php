<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Runtime localization
    |--------------------------------------------------------------------------
    |
    | When enabled, outgoing HTML responses are translated from English to
    | Chinese using the phrase dictionaries in resources/localization/zh-cn.
    | Set LOCALIZE_OFF=true to disable the translation layer.
    |
    */

    'enabled' => ! (bool) env('LOCALIZE_OFF', false),

];
