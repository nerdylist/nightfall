<?php
/**
 * Data-access layer (DAL) for the Nexus forum.
 *
 * Each function returns plain PHP arrays whose shape matches data/mock.php
 * EXACTLY (same keys, same per-item structure). SQLite/PDO returns integer
 * columns as strings, so every id-type column is explicitly cast to (int)
 * in every returned row — templates rely on === comparisons against integer
 * ids.
 */

require_once __DIR__ . '/../db.php';

if (!function_exists('get_users')) {

    /**
     * All users, ordered by id.
     *
     * @return array<int,array<string,mixed>>
     */
    function get_users()
    {
        $rows = forum_db()->query(
            "SELECT id, username,
                    COALESCE(NULLIF(display_name, ''), username) AS display_name,
                    COALESCE(bio, '') AS bio,
                    COALESCE(join_date, date(created_at)) AS join_date,
                    reputation, threads_started, chat_messages
             FROM host.users
             ORDER BY id"
        )->fetchAll();

        return array_map('forum_shape_user', $rows);
    }

    /**
     * A single user by id, or null.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    function get_user($id)
    {
        $stmt = forum_db()->prepare(
            "SELECT id, username,
                    COALESCE(NULLIF(display_name, ''), username) AS display_name,
                    COALESCE(bio, '') AS bio,
                    COALESCE(join_date, date(created_at)) AS join_date,
                    reputation, threads_started, chat_messages
             FROM host.users
             WHERE id = :id"
        );
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row ? forum_shape_user($row) : null;
    }

    /**
     * All categories, ordered by sort_order then id.
     *
     * @return array<int,array<string,mixed>>
     */
    function get_categories()
    {
        $rows = forum_db()->query(
            'SELECT id, name, description, icon, color, thread_count, post_count, last_activity, featured
             FROM categories
             ORDER BY sort_order, id'
        )->fetchAll();

        return array_map('forum_shape_category', $rows);
    }

    /**
     * A single category by id, or null.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    function get_category($id)
    {
        $stmt = forum_db()->prepare(
            'SELECT id, name, description, icon, color, thread_count, post_count, last_activity, featured
             FROM categories
             WHERE id = :id'
        );
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row ? forum_shape_category($row) : null;
    }

    /**
     * All threads, ordered by id.
     *
     * @return array<int,array<string,mixed>>
     */
    function get_threads()
    {
        $rows = forum_db()->query(
            'SELECT id, category_id, title, author_id, replies, views, last_activity, pinned, hot, excerpt
             FROM threads
             ORDER BY id'
        )->fetchAll();

        return array_map('forum_shape_thread', $rows);
    }

    /**
     * Threads belonging to one category, ordered by id.
     *
     * @param int $id
     * @return array<int,array<string,mixed>>
     */
    function get_threads_by_category($id)
    {
        $stmt = forum_db()->prepare(
            'SELECT id, category_id, title, author_id, replies, views, last_activity, pinned, hot, excerpt
             FROM threads
             WHERE category_id = :id
             ORDER BY id'
        );
        $stmt->execute([':id' => (int) $id]);

        return array_map('forum_shape_thread', $stmt->fetchAll());
    }

    /**
     * A single thread by id, or null.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    function get_thread($id)
    {
        $stmt = forum_db()->prepare(
            'SELECT id, category_id, title, author_id, replies, views, last_activity, pinned, hot, excerpt
             FROM threads
             WHERE id = :id'
        );
        $stmt->execute([':id' => (int) $id]);
        $row = $stmt->fetch();

        return $row ? forum_shape_thread($row) : null;
    }

    /**
     * First (lowest id) post for a thread, or null.
     *
     * @param int $threadId
     * @return array<string,mixed>|null
     */
    function get_post_for_thread($threadId)
    {
        $stmt = forum_db()->prepare(
            'SELECT thread_id, author_id, body, created
             FROM posts
             WHERE thread_id = :id
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([':id' => (int) $threadId]);
        $row = $stmt->fetch();

        return $row ? forum_shape_post($row) : null;
    }

    /**
     * Chat messages for a thread, ordered by id.
     *
     * @param int $threadId
     * @return array<int,array<string,mixed>>
     */
    function get_chat_for_thread($threadId)
    {
        $stmt = forum_db()->prepare(
            'SELECT id, thread_id, author_id, timestamp, text
             FROM chat_messages
             WHERE thread_id = :id
             ORDER BY id ASC'
        );
        $stmt->execute([':id' => (int) $threadId]);

        return array_map('forum_shape_chat', $stmt->fetchAll());
    }

    /**
     * All posts in id order (for live.php aggregation).
     *
     * @return array<int,array<string,mixed>>
     */
    function get_all_posts()
    {
        $rows = forum_db()->query(
            'SELECT thread_id, author_id, body, created
             FROM posts
             ORDER BY id ASC'
        )->fetchAll();

        return array_map('forum_shape_post', $rows);
    }

    /**
     * All chat messages in id order (for live.php aggregation).
     *
     * @return array<int,array<string,mixed>>
     */
    function get_all_chat()
    {
        $rows = forum_db()->query(
            'SELECT id, thread_id, author_id, timestamp, text
             FROM chat_messages
             ORDER BY id ASC'
        )->fetchAll();

        return array_map('forum_shape_chat', $rows);
    }

    /**
     * The id of the current session user, or 0 for guests.
     *
     * @return int
     */
    function get_current_user_id()
    {
        require_once __DIR__ . '/../lib/auth.php';
        return auth_is_logged_in() ? (int) auth_current_user()['id'] : 0;
    }

    // --- Mutations ----------------------------------------------------------

    /**
     * Create a new thread and its first (original) post atomically.
     *
     * Inserts one row into `threads` and one row into `posts` inside a single
     * transaction, and keeps the denormalized counters the rest of the app
     * reads (users.threads_started, categories.thread_count, categories.post_count)
     * consistent. On any failure the whole operation is rolled back.
     *
     * Machine timestamp columns (created_at / updated_at) use the same ISO 8601
     * format the seeder writes (gmdate('c')). The human-facing display columns
     * (threads.last_activity, posts.created) are set to "just now" to match the
     * relative-time strings the seed data uses and that the templates render
     * verbatim.
     *
     * @param int    $categoryId  Existing category id.
     * @param int    $authorId    Existing user id (thread + post author).
     * @param string $title       Thread title.
     * @param string $body        Original post body.
     * @param string $excerpt     Short excerpt shown in thread lists.
     * @return int                The id of the newly created thread.
     *
     * @throws InvalidArgumentException When title or body is empty after trim.
     */
    function create_thread($categoryId, $authorId, $title, $body, $excerpt)
    {
        $categoryId = (int) $categoryId;
        $authorId   = (int) $authorId;
        $title      = trim((string) $title);
        $body       = trim((string) $body);
        $excerpt    = (string) $excerpt;

        if ($title === '') {
            throw new InvalidArgumentException('Thread title must not be empty.');
        }
        if ($body === '') {
            throw new InvalidArgumentException('Post body must not be empty.');
        }

        $db  = forum_db();
        $now = gmdate('c');     // machine timestamp (matches seeder)
        $rel = 'just now';      // human-facing display string (matches seed style)

        $db->beginTransaction();

        try {
            $tStmt = $db->prepare(
                'INSERT INTO threads
                    (category_id, author_id, title, excerpt, replies, views, pinned, locked, hot, last_activity, created_at, updated_at)
                 VALUES
                    (:category_id, :author_id, :title, :excerpt, 0, 0, 0, 0, 0, :last_activity, :created_at, :updated_at)'
            );
            $tStmt->execute([
                ':category_id'   => $categoryId,
                ':author_id'     => $authorId,
                ':title'         => $title,
                ':excerpt'       => $excerpt,
                ':last_activity' => $rel,
                ':created_at'    => $now,
                ':updated_at'    => $now,
            ]);

            $threadId = (int) $db->lastInsertId();

            $pStmt = $db->prepare(
                'INSERT INTO posts
                    (thread_id, author_id, body, created, created_at)
                 VALUES
                    (:thread_id, :author_id, :body, :created, :created_at)'
            );
            $pStmt->execute([
                ':thread_id'  => $threadId,
                ':author_id'  => $authorId,
                ':body'       => $body,
                ':created'    => $rel,
                ':created_at' => $now,
            ]);

            // Keep denormalized counters the rest of the app reads in sync.
            $db->prepare('UPDATE host.users SET threads_started = threads_started + 1 WHERE id = :id')
               ->execute([':id' => $authorId]);

            $db->prepare(
                'UPDATE categories
                    SET thread_count = thread_count + 1,
                        post_count   = post_count + 1,
                        last_activity = :rel
                  WHERE id = :id'
            )->execute([':rel' => $rel, ':id' => $categoryId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return (int) $threadId;
    }

    /**
     * Create a new live-chat message for a thread atomically.
     *
     * Inserts one row into `chat_messages` and keeps the denormalized counter
     * the rest of the app reads (users.chat_messages) consistent, inside a
     * single transaction. On any failure the whole operation is rolled back.
     *
     * The machine timestamp column (created_at) uses the same ISO 8601 format
     * the seeder writes (gmdate('c')). The human-facing display column
     * (chat_messages.timestamp) is set to a "10:42 AM" clock string to match
     * the seed data the templates render verbatim.
     *
     * @param int    $threadId  Existing thread id.
     * @param int    $authorId  Existing user id (message author).
     * @param string $text      Message body.
     * @return int              The id of the newly created chat message.
     *
     * @throws InvalidArgumentException When text is empty after trim.
     */
    function create_chat_message($threadId, $authorId, $text)
    {
        $threadId = (int) $threadId;
        $authorId = (int) $authorId;
        $text     = trim((string) $text);

        if ($text === '') {
            throw new InvalidArgumentException('Chat message must not be empty.');
        }

        $db      = forum_db();
        $now     = gmdate('c');     // machine timestamp (matches seeder)
        $display = date('g:i A');   // human-facing clock string (matches "10:42 AM")

        $db->beginTransaction();

        try {
            $cStmt = $db->prepare(
                'INSERT INTO chat_messages
                    (thread_id, author_id, text, timestamp, created_at)
                 VALUES
                    (:thread_id, :author_id, :text, :timestamp, :created_at)'
            );
            $cStmt->execute([
                ':thread_id'  => $threadId,
                ':author_id'  => $authorId,
                ':text'       => $text,
                ':timestamp'  => $display,
                ':created_at' => $now,
            ]);

            $id = (int) $db->lastInsertId();

            // Keep the denormalized counter the rest of the app reads in sync.
            $db->prepare('UPDATE host.users SET chat_messages = chat_messages + 1 WHERE id = :id')
               ->execute([':id' => $authorId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return (int) $id;
    }

    // --- Internal shapers: enforce key set + integer ids --------------------

    /** @param array<string,mixed> $r @return array<string,mixed> */
    function forum_shape_user($r)
    {
        return [
            'id'              => (int) $r['id'],
            'username'        => $r['username'],
            'display_name'    => $r['display_name'],
            'bio'             => $r['bio'],
            'join_date'       => $r['join_date'],
            'reputation'      => $r['reputation'],
            'threads_started' => $r['threads_started'],
            'chat_messages'   => $r['chat_messages'],
        ];
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    function forum_shape_category($r)
    {
        return [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'description'   => $r['description'],
            'icon'          => $r['icon'],
            'color'         => $r['color'],
            'thread_count'  => $r['thread_count'],
            'post_count'    => $r['post_count'],
            'last_activity' => $r['last_activity'],
            'featured'      => (int)$r['featured'],
        ];
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    function forum_shape_thread($r)
    {
        return [
            'id'            => (int) $r['id'],
            'category_id'   => (int) $r['category_id'],
            'title'         => $r['title'],
            'author_id'     => (int) $r['author_id'],
            'replies'       => $r['replies'],
            'views'         => $r['views'],
            'last_activity' => $r['last_activity'],
            'pinned'        => $r['pinned'],
            'hot'           => $r['hot'],
            'excerpt'       => $r['excerpt'],
        ];
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    function forum_shape_post($r)
    {
        return [
            'thread_id' => (int) $r['thread_id'],
            'author_id' => (int) $r['author_id'],
            'body'      => $r['body'],
            'created'   => $r['created'],
        ];
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    function forum_shape_chat($r)
    {
        return [
            'id'        => (int) $r['id'],
            'thread_id' => (int) $r['thread_id'],
            'author_id' => (int) $r['author_id'],
            'timestamp' => $r['timestamp'],
            'text'      => $r['text'],
        ];
    }
}
