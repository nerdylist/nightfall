<?php
/**
 * Live, DB-backed data source.
 *
 * Drop-in replacement for data/mock.php: returns the same top-level $data
 * array shape (current_user, users, categories, threads, posts,
 * chat_messages) but resolved from the SQLite database via the DAL.
 */

require_once __DIR__ . '/db.php';

return [
    'current_user'  => get_current_user_id(),   // integer 1
    'users'         => get_users(),
    'categories'    => get_categories(),
    'threads'       => get_threads(),
    'posts'         => get_all_posts(),
    'chat_messages' => get_all_chat(),
];
