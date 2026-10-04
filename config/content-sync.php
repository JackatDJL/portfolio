<?php

return [
    'repository' => env('CONTENT_SYNC_REPOSITORY', '/sync'),
    'branch' => env('CONTENT_SYNC_BRANCH', 'content-sync'),
    'remote' => env('CONTENT_SYNC_REMOTE', 'origin'),
    'lock_path' => env('CONTENT_SYNC_LOCK_PATH', storage_path('framework/locks/content-sync.lock')),
    'candidate_path' => env('CONTENT_SYNC_CANDIDATE_PATH', storage_path('app/content-sync-candidate')),
    'candidate_branch_prefix' => 'portfolio-content-sync-candidate-',
    'ssh_key' => env('CONTENT_SYNC_SSH_KEY'),
    'known_hosts' => env('CONTENT_SYNC_KNOWN_HOSTS'),
    'author_name' => env('CONTENT_SYNC_GIT_AUTHOR_NAME', 'Portfolio Content Sync'),
    'author_email' => env('CONTENT_SYNC_GIT_AUTHOR_EMAIL', 'portfolio-content-sync@users.noreply.github.com'),
    'stache_pending_path' => storage_path('app/content-sync-stache-pending'),
];
