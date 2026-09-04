<?php

namespace App\Services;

use App\Models\Post;

class FacebookPostFormatter
{
    public function format(Post $post): string
    {
        $parts = [];
        
        if (!empty($post->title)) {
            $parts[] = trim($post->title);
        }
        
        if (!empty($post->content)) {
            $parts[] = trim($post->content);
        }
        
        if (!empty($post->cta)) {
            $parts[] = trim($post->cta);
        }

        if (!empty($post->hashtags) && is_array($post->hashtags)) {
            $hashtags = array_map(function($tag) {
                $tag = trim($tag);
                return str_starts_with($tag, '#') ? $tag : '#' . $tag;
            }, $post->hashtags);
            
            // Filter out empty hashtags
            $hashtags = array_filter($hashtags);
            
            if (!empty($hashtags)) {
                $parts[] = implode(' ', $hashtags);
            }
        }

        // Join with exactly two newlines, then replace 3+ newlines with 2 newlines just in case
        $message = implode("\n\n", $parts);
        $message = preg_replace("/\n{3,}/", "\n\n", $message);

        return $message;
    }
}
