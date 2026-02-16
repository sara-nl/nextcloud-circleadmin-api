<?php

return [
    'ocs' => [
        // Circles
        ['name' => 'CircleApi#index',      'url' => '/api/v1/circles',              'verb' => 'GET'],
        ['name' => 'CircleApi#show',       'url' => '/api/v1/circles/{circleId}',   'verb' => 'GET'],
        ['name' => 'CircleApi#create',     'url' => '/api/v1/circles',              'verb' => 'POST'],
        ['name' => 'CircleApi#update',     'url' => '/api/v1/circles/{circleId}',   'verb' => 'PUT'],
        ['name' => 'CircleApi#destroy',    'url' => '/api/v1/circles/{circleId}',   'verb' => 'DELETE'],

        // Members
        ['name' => 'MemberApi#index',      'url' => '/api/v1/circles/{circleId}/members',              'verb' => 'GET'],
        ['name' => 'MemberApi#add',        'url' => '/api/v1/circles/{circleId}/members',              'verb' => 'POST'],
        ['name' => 'MemberApi#remove',     'url' => '/api/v1/circles/{circleId}/members/{memberId}',   'verb' => 'DELETE'],
        ['name' => 'MemberApi#setLevel',   'url' => '/api/v1/circles/{circleId}/members/{memberId}/level', 'verb' => 'PUT'],
    ],
];
