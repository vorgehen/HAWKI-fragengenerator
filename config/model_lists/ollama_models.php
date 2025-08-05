<?php

return [
    [
        'active'=> true,
        'id' => 'llama3.2',
        'label' => 'IXD Llama 3.2',
        "input"=> [
            "text",
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'image_generation' => true,
            'vision' => true,
            'internet_search' => true,
            'file_upload' => true,
        ],
        'streamable' => true,

    ],
    [
        'active'=> true,
        'id' => 'tinyllama',
        'label' => 'IXD TinyLlama',
        "input"=> [
            "text",
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            'stream' => true,
            'image_generation' => true,
            'vision' => true,
            'internet_search' => true,
            'file_upload' => true,
        ],
        'streamable' => true,

    ],
];
