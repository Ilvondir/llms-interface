<?php

namespace App\Support\Chat;

/**
 * Shared size caps for chat message content (stream proxy + prompt persistence).
 */
final class ChatContentLimits
{
    /**
     * Max characters for a single message content (legacy string or serialized parts).
     * Sized for one client-compressed image data-URL plus text headroom.
     */
    public const MAX_CONTENT_CHARS = 5_500_000;

    /**
     * Legacy alias used by prompt FormRequests for non-image text fields (reasoning, etc.).
     */
    public const MAX_TEXT_CHARS = 100_000;

    public const MAX_JSON_BYTES = 100_000;

    public const MAX_IMAGES_PER_MESSAGE = 1;

    /** Max messages in one chat.stream request (history + new turn). */
    public const MAX_MESSAGES_PER_REQUEST = 100;

    /** Max JSON-encoded size of the messages array on chat.stream. */
    public const MAX_STREAM_MESSAGES_BYTES = 6_000_000;

    public const IMAGE_DATA_URL_PATTERN = '/^data:image\/(jpeg|png|gif|webp);base64,/i';
}
