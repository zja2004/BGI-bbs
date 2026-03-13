#!/usr/bin/env php
<?php
$site = require __DIR__ . '/site.php';

// Boot the app - this returns an InstalledApp which already has the container
$app = $site->bootApp();

// Get the container
$container = $app->getContainer();

// Get the formatter
$formatter = $container->make(Flarum\Formatter\Formatter::class);

// Get post repository
$postRepository = $container->make(Flarum\Post\PostRepository::class);

// Posts to reparse
$postIds = [6, 10, 11, 12, 13, 14];

foreach ($postIds as $postId) {
    try {
        $post = $postRepository->findOrFail($postId);
        if ($post instanceof Flarum\Post\CommentPost) {
            // Get current XML content
            $xmlContent = $post->getParsedContentAttribute();
            
            // Unparse to get markdown
            $markdown = $formatter->unparse($xmlContent, $post);
            
            // Re-parse
            $newXml = $formatter->parse($markdown, $post, $post->user);
            
            // Update
            $post->setParsedContentAttribute($newXml);
            $post->save();
            
            echo "✓ Reparsed post ID: $postId\n";
        }
    } catch (Exception $e) {
        echo "✗ Error with post $postId: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";
