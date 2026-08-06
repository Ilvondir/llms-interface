<?php

return [
    'timeout' => (int) env('LLMS_CHAT_TIMEOUT', 600),
    'models_timeout' => (int) env('LLMS_MODELS_TIMEOUT', 30),
    'connect_timeout' => (int) env('LLMS_CONNECT_TIMEOUT', 10),
    'throttle_per_minute' => (int) env('LLMS_THROTTLE_PER_MINUTE', 60),
    // MCP limits (foundation + toolkit); rounds/timeout used from phase 2+.
    'mcp_max_servers' => (int) env('LLMS_MCP_MAX_SERVERS', 10),
    'mcp_max_tool_rounds' => (int) env('LLMS_MCP_MAX_TOOL_ROUNDS', 5),
    'mcp_client_timeout' => (int) env('LLMS_MCP_CLIENT_TIMEOUT', 30),
];
