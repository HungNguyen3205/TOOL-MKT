<?php
$pages = \App\Models\FacebookPage::all();
foreach(\App\Models\Post::all() as $post) {
    if ($post->facebook_page_id && !is_numeric($post->facebook_page_id)) {
        $page = $pages->where('page_name', $post->facebook_page_id)->first();
        if ($page) {
            $post->facebook_page_id = (string)$page->id;
            $post->save();
        }
    } elseif ($post->facebook_page_id && strlen((string)$post->facebook_page_id) > 10) {
        $page = $pages->where('page_id', $post->facebook_page_id)->first();
        if ($page) {
            $post->facebook_page_id = (string)$page->id;
            $post->save();
        }
    }
}
echo "Done\n";
