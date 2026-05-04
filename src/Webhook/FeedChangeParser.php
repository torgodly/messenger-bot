<?php

namespace MessengerBot\Webhook;

use MessengerBot\Comments\Comment;
use MessengerBot\Comments\CommentAuthor;

class FeedChangeParser
{
    /**
     * @param  array<string, mixed>  $change
     */
    public function parseComment(array $change): ?Comment
    {
        if (($change['field'] ?? '') !== 'feed') {
            return null;
        }

        $value = $change['value'] ?? null;
        if (! is_array($value)) {
            return null;
        }

        if (($value['item'] ?? '') !== 'comment') {
            return null;
        }

        $verb = (string) ($value['verb'] ?? 'add');
        if ($verb !== 'add') {
            return null;
        }

        $commentId = $value['comment_id'] ?? $value['id'] ?? null;
        if ($commentId === null && isset($value['comment']) && is_array($value['comment'])) {
            $commentId = $value['comment']['id'] ?? null;
        }

        if ($commentId === null) {
            return null;
        }

        $message = isset($value['message']) ? (string) $value['message'] : null;
        if ($message === null && isset($value['comment']['message'])) {
            $message = (string) $value['comment']['message'];
        }

        $fromId = '';
        $fromName = null;
        if (isset($value['from']) && is_array($value['from'])) {
            $fromId = (string) ($value['from']['id'] ?? '');
            $fromName = isset($value['from']['name']) ? (string) $value['from']['name'] : null;
        } elseif (isset($value['sender_id'])) {
            $fromId = (string) $value['sender_id'];
            $fromName = isset($value['sender_name']) ? (string) $value['sender_name'] : null;
        } elseif (isset($value['comment']['from']) && is_array($value['comment']['from'])) {
            $fromId = (string) ($value['comment']['from']['id'] ?? '');
            $fromName = isset($value['comment']['from']['name']) ? (string) $value['comment']['from']['name'] : null;
        }

        $postId = isset($value['post_id']) ? (string) $value['post_id'] : null;
        $parentId = isset($value['parent_id']) ? (string) $value['parent_id'] : null;
        if ($parentId === null && isset($value['comment']) && is_array($value['comment'])) {
            $nested = $value['comment'];
            if (isset($nested['parent']['id'])) {
                $parentId = (string) $nested['parent']['id'];
            } elseif (isset($nested['parent_id'])) {
                $parentId = (string) $nested['parent_id'];
            }
        }
        $created = isset($value['created_time']) ? (string) $value['created_time'] : null;
        $canPrivate = null;
        if (array_key_exists('can_reply_privately', $value)) {
            $canPrivate = (bool) $value['can_reply_privately'];
        }

        return new Comment(
            (string) $commentId,
            $message,
            new CommentAuthor($fromId, $fromName),
            $postId,
            $parentId,
            $created,
            $canPrivate,
            $change,
        );
    }
}
