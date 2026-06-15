<?php

/**
 * Create a cURL write callback that echoes chunks in real-time.
 *
 * @return callable
 */
function create_stream_writer(): callable
{
    return function ($ch, string $data): int {
        echo $data;
        flush();
        return strlen($data);
    };
}
