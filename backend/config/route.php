<?php

declare(strict_types=1);

return [
    'pathinfo_depr' => '/',
    'url_html_suffix' => '',
    'url_common_param' => true,
    'url_lazy_route' => false,
    'url_route_must' => true,
    'route_rule_merge' => false,
    'route_complete_match' => true,
    'controller_layer' => 'controller',
    'empty_controller' => 'Error',
    'controller_suffix' => false,
    'default_route_pattern' => '[\w\.-]+',
    'request_cache_key' => false,
    'request_cache_expire' => null,
    'request_cache_except' => [],
    'default_controller' => 'Index',
    'default_action' => 'index',
    'action_suffix' => '',
    'default_jsonp_handler' => 'jsonpReturn',
    'var_jsonp_handler' => 'callback',
];
