<?php

    return [
        'buckets' => [
            'user' => env('MINIO_USER_BUCKET', 'user-files'),
            'group' => env('MINIO_GROUP_BUCKET', 'group-files'),
        ],
    ];