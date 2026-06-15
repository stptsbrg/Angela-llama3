<?php

return [
    'groq' => [
        'api_key' => getenv('GROQ_API_KEY'),
        'base_url' => 'https://api.groq.com/openai/v1',
        'model' => 'llama3-8b-8192',
    ],
];
