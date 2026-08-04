<?php

return [
    'timeout' => (int) env('LLMS_CHAT_TIMEOUT', 300),
    'models_timeout' => (int) env('LLMS_MODELS_TIMEOUT', 30),
    'connect_timeout' => (int) env('LLMS_CONNECT_TIMEOUT', 10),
];
