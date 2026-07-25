<?php
require __DIR__ . '/config.php';              // exposes $CONFIG
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
$data = require __DIR__ . '/data/live.php';   // returns mock array -> $data
$me = auth_current_user();
$data['current_user'] = $me ? (int)$me['id'] : 0;
require_once __DIR__ . '/partials/avatar.php';
require_once __DIR__ . '/lib/bbcode.php';
require_once __DIR__ . '/partials/category-badge.php';

// friendly-URL: /bbs/thread/:id exposes id via $_ROUTE_PARAMS; bridge to $_GET
if (isset($GLOBALS['_ROUTE_PARAMS']['id']) && !isset($_GET['id'])) { $_GET['id'] = $GLOBALS['_ROUTE_PARAMS']['id']; }

// ---- STAFF DELETE (Boss 2026-07-25): admins/moderators can delete a whole
// thread from the main-post footer. Role-gated HARD server-side — the button
// is also hidden in markup for everyone else, but this check is the law.
$canModerate = $me !== null
    && in_array(($me['role'] ?? ''), ['admin', 'moderator'], true)
    && (($me['status'] ?? '') === 'active');

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['action'] ?? '') === 'delete_thread')) {
    if (!$canModerate || !csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }

    require_once __DIR__ . '/db.php';
    $delId = (int) ($_POST['thread_id'] ?? 0);
    $fdb = forum_db();

    $catStmt = $fdb->prepare('SELECT category_id FROM threads WHERE id = ?');
    $catStmt->execute([$delId]);
    $delCat = (int) $catStmt->fetchColumn();

    // Full footprint, children first: reactions -> posts -> chat -> thread.
    $fdb->prepare('DELETE FROM reactions WHERE post_id IN (SELECT id FROM posts WHERE thread_id = ?)')
        ->execute([$delId]);
    $fdb->prepare('DELETE FROM posts WHERE thread_id = ?')->execute([$delId]);
    $fdb->prepare('DELETE FROM chat_messages WHERE thread_id = ?')->execute([$delId]);
    $fdb->prepare('DELETE FROM threads WHERE id = ?')->execute([$delId]);

    header('Location: ' . ($BASE ?? '/bbs/') . ($delCat > 0 ? 'category/' . $delCat : ''));
    exit;
}

// ---- LOCK TOGGLE (Boss 2026-07-25): OP on their own thread, or staff on
// any. Locked = the live chat only accepts the ORIGINAL POSTER's messages
// (enforced server-side in chat.php; the UI also hides the composer).
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['action'] ?? '') === 'toggle_lock')) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Forbidden');
    }

    require_once __DIR__ . '/db.php';
    $lockId = (int) ($_POST['thread_id'] ?? 0);
    $fdb = forum_db();
    $row = $fdb->prepare('SELECT author_id, locked FROM threads WHERE id = ?');
    $row->execute([$lockId]);
    $lockThread = $row->fetch();

    $isOwner = $lockThread && $me !== null
        && (int) $me['id'] === (int) $lockThread['author_id'];
    if (!$lockThread || (!$isOwner && !$canModerate)) {
        http_response_code(403);
        exit('Forbidden');
    }

    $fdb->prepare('UPDATE threads SET locked = ? WHERE id = ?')
        ->execute([((int) $lockThread['locked']) === 1 ? 0 : 1, $lockId]);

    header('Location: ' . ($BASE ?? '/bbs/') . 'thread/' . $lockId);
    exit;
}

// Resolve requested thread id (default to first thread).
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($data['threads'][0]['id'] ?? 0);

$thread = null;
foreach ($data['threads'] as $t) {
    if ((int) $t['id'] === $requestedId) {
        $thread = $t;
        break;
    }
}
if ($thread === null) {
    $thread = $data['threads'][0] ?? null;
}
$threadId = $thread !== null ? (int) $thread['id'] : 0;

// Resolve the thread's category for the breadcrumb above the title.
$threadCategory = null;
foreach ($data['categories'] as $c) {
    if ((int) $c['id'] === (int) ($thread['category_id'] ?? 0)) {
        $threadCategory = $c;
        break;
    }
}

// Find the original post for this thread.
$post = null;
foreach ($data['posts'] as $p) {
    if ((int) $p['thread_id'] === $threadId) {
        $post = $p;
        break;
    }
}

// Resolve the original post author.
$postAuthor = ['display_name' => 'Unknown'];
$authorId = $post['author_id'] ?? ($thread['author_id'] ?? 0);
foreach ($data['users'] as $u) {
    if ((int) $u['id'] === (int) $authorId) {
        $postAuthor = $u;
        break;
    }
}

// Resolve the current user for the chat composer.
$currentUser = $data['users'][0] ?? ['display_name' => 'You'];
foreach ($data['users'] as $u) {
    if ((int) $u['id'] === (int) $data['current_user']) {
        $currentUser = $u;
        break;
    }
}

$currentUserName = (string) ($currentUser['display_name'] ?? 'You');
$currentUserInitials = forum_avatar_initials($currentUserName);

// Gather chat messages for this thread.
$threadChat = [];
foreach ($data['chat_messages'] as $m) {
    if ((int) $m['thread_id'] === $threadId) {
        $threadChat[] = $m;
    }
}
$lastChatId = 0;
foreach ($threadChat as $m) {
    if ((int) $m['id'] > $lastChatId) {
        $lastChatId = (int) $m['id'];
    }
}

// Index users by id for quick chat author lookups.
$usersById = [];
foreach ($data['users'] as $u) {
    $usersById[(int) $u['id']] = $u;
}

include __DIR__ . '/partials/head.php';       // DOCTYPE..head..</head><body>
include __DIR__ . '/partials/header.php';     // <header class="site-header">
?>
<main class="container">
  <?php
    $body = (string) ($post['body'] ?? $thread['excerpt'] ?? '');
    $paragraphs = preg_split('/\n\n+/', trim($body));
    $postMeta = (string) ($post['created'] ?? ($thread['last_activity'] ?? ''));
    $postAuthorName = (string) ($postAuthor['display_name'] ?? 'Unknown');
  ?>
  <div class="thread-layout">
  <article class="original-post">
    <header class="op-header">
      <div class="op-top">
        <div class="op-byline">
          <?php render_avatar($postAuthorName, 48); ?>
          <span class="op-author"><?= htmlspecialchars($postAuthorName) ?></span>
        </div>
        <?php if ($threadCategory !== null): ?>
          <a class="op-category" href="/bbs/category/<?= (int)$threadCategory['id'] ?>" style="--cat-color: <?= forum_category_color($threadCategory) ?>;">
            <span class="op-category-badge<?= forum_category_badge_is_image($threadCategory) ? ' is-image' : '' ?>"><?= forum_category_badge($threadCategory) ?></span>
            <span class="op-category-name"><?= htmlspecialchars($threadCategory['name']) ?></span>
          </a>
        <?php endif; ?>
      </div>
      <?php $opTitle = htmlspecialchars($thread['title'] ?? 'Thread'); ?>
      <h1 class="op-title forum-title-layered" data-title="<?= $opTitle ?>"><?= $opTitle ?></h1>
      <div class="op-stats">
        <span><?= (int)($thread['replies'] ?? 0) ?> replies</span>
        <span><?= (int)($thread['views'] ?? 0) ?> views</span>
        <span><?= htmlspecialchars($postMeta) ?></span>
        <span><?= htmlspecialchars($thread['last_activity'] ?? '') ?></span>
      </div>
    </header>
    <div class="op-content">
      <div class="post-body">
        <?= bbcode_to_html($body) ?>
      </div>
    </div>
    <div class="post-actions" data-thread-title="<?= htmlspecialchars($thread['title'] ?? 'Thread') ?>">
      <!-- FA icons from icons.txt (Boss 2026-07-25) — no hand-drawn SVGs. -->
      <button class="post-action" type="button" data-action="share" aria-label="Share" title="Share">
        <img class="post-action-icon" src="https://nerd.biz/assets/fa/svgs/solid/share-nodes.svg" width="17" height="17" alt="" aria-hidden="true">
      </button>
      <button class="post-action" type="button" data-action="copy" aria-label="Copy link" title="Copy link">
        <img class="post-action-icon" src="https://nerd.biz/assets/fa/svgs/solid/link.svg" width="17" height="17" alt="" aria-hidden="true">
      </button>
      <div class="reaction-wrap">
        <button class="post-action" type="button" data-action="react" aria-expanded="false" aria-haspopup="true" aria-label="React" title="React">
          <img class="post-action-icon" src="https://nerd.biz/assets/fa/svgs/solid/face-smile.svg" width="17" height="17" alt="" aria-hidden="true">
        </button>
        <div class="reaction-picker" hidden role="menu" aria-label="Pick a reaction">
          <button class="reaction" type="button" role="menuitem" data-emoji="👍">👍</button>
          <button class="reaction" type="button" role="menuitem" data-emoji="❤️">❤️</button>
          <button class="reaction" type="button" role="menuitem" data-emoji="😂">😂</button>
          <button class="reaction" type="button" role="menuitem" data-emoji="😮">😮</button>
          <button class="reaction" type="button" role="menuitem" data-emoji="😢">😢</button>
          <button class="reaction" type="button" role="menuitem" data-emoji="🔥">🔥</button>
        </div>
      </div>
      <?php
      // LOCK (Boss 2026-07-25): OP can lock/unlock their own thread; staff
      // can lock/unlock any. Locked = chat accepts ONLY the OP's messages.
      $isThreadOwner = $me !== null && (int) $me['id'] === (int) ($thread['author_id'] ?? 0);
      $threadLocked = (int) ($thread['locked'] ?? 0) === 1;
      ?>
      <?php if ($isThreadOwner || $canModerate): ?>
      <form class="post-lock-form" method="post" action="<?= htmlspecialchars(($BASE ?? '/bbs/') . 'thread/' . $threadId) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_lock">
        <input type="hidden" name="thread_id" value="<?= (int) $threadId ?>">
        <button class="post-action" type="submit" aria-label="<?= $threadLocked ? 'Unlock thread chat' : 'Lock thread chat' ?>" title="<?= $threadLocked ? 'Unlock chat (everyone can post)' : 'Lock chat (only you can post)' ?>">
          <img class="post-action-icon" src="https://nerd.biz/assets/fa/svgs/solid/<?= $threadLocked ? 'lock' : 'lock-open' ?>.svg" width="17" height="17" alt="" aria-hidden="true">
        </button>
      </form>
      <?php endif; ?>
      <?php if ($canModerate): ?>
      <form class="post-delete-form" method="post" action="<?= htmlspecialchars(($BASE ?? '/bbs/') . 'thread/' . $threadId) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_thread">
        <input type="hidden" name="thread_id" value="<?= (int) $threadId ?>">
        <button class="post-action post-action--danger" type="submit" data-action="delete-thread" aria-label="Delete thread" title="Delete thread (staff)">
          <img class="post-action-icon" src="https://nerd.biz/assets/fa/svgs/solid/trash-can.svg" width="17" height="17" alt="" aria-hidden="true">
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php
    // PERSISTED REACTIONS (2026-07-25): render stored chips on load; the
    // picker/chips JS then toggles via react.php and rebuilds from JSON.
    $reactionCounts = [];
    $myReactions = [];
    $mainPostId = (int) ($post['id'] ?? 0);
    if ($mainPostId > 0) {
        require_once __DIR__ . '/db.php';
        $rdb = forum_db();
        $rq = $rdb->prepare('SELECT emoji, COUNT(*) AS n FROM reactions WHERE post_id = ? GROUP BY emoji');
        $rq->execute([$mainPostId]);
        foreach ($rq->fetchAll(PDO::FETCH_ASSOC) as $rrow) {
            $reactionCounts[$rrow['emoji']] = (int) $rrow['n'];
        }
        if ($me !== null) {
            $mq = $rdb->prepare('SELECT emoji FROM reactions WHERE post_id = ? AND user_id = ?');
            $mq->execute([$mainPostId, (int) $me['id']]);
            $myReactions = $mq->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    ?>
    <div class="post-reactions" aria-label="Reactions"
         data-post-id="<?= $mainPostId ?>"
         data-endpoint="<?= htmlspecialchars(($BASE ?? '/bbs/') . 'react.php') ?>"
         data-csrf="<?= htmlspecialchars(csrf_token()) ?>"
         data-can-react="<?= $me !== null ? '1' : '' ?>">
      <?php foreach ($reactionCounts as $rEmoji => $rCount): ?>
        <button type="button"
                class="reaction-chip<?= in_array($rEmoji, $myReactions, true) ? ' active' : '' ?>"
                data-emoji="<?= htmlspecialchars($rEmoji) ?>"
                aria-label="Toggle <?= htmlspecialchars($rEmoji) ?> reaction">
          <span class="reaction-chip-emoji"><?= htmlspecialchars($rEmoji) ?></span>
          <span class="reaction-chip-count"><?= $rCount ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </article>

  <?php
  // LOCKED CHAT (Boss 2026-07-25): when the thread is locked, only the OP
  // may post — everyone else (including staff) reads. chat.php enforces
  // this server-side; here we gate the composer + the data-can-post flag.
  $chatMayPost = auth_is_logged_in()
      && (!$threadLocked || ($me !== null && (int) $me['id'] === (int) ($thread['author_id'] ?? 0)));
  ?>
  <section class="chat" data-user-name="<?= htmlspecialchars($currentUserName) ?>" data-user-initials="<?= htmlspecialchars($currentUserInitials) ?>" data-user-id="<?= (int)$data['current_user'] ?>" data-thread-id="<?= (int)$threadId ?>" data-csrf="<?= htmlspecialchars(csrf_token()) ?>" data-can-post="<?= $chatMayPost ? '1' : '' ?>" data-last-id="<?= (int)$lastChatId ?>" data-endpoint="<?= htmlspecialchars(($BASE ?? '/bbs/') . 'chat.php') ?>">
    <div class="chat-header"><span>Live Chat</span><?php if ($threadLocked): ?><span class="chat-locked-badge"><img src="https://nerd.biz/assets/fa/svgs/solid/lock.svg" width="11" height="11" alt="" aria-hidden="true"> OP only</span><?php endif; ?></div>
    <div class="chat-messages" aria-live="polite">
      <?php if (empty($threadChat)): ?>
        <p class="chat-empty">No messages yet.</p>
      <?php else: ?>
        <?php foreach ($threadChat as $message): ?>
          <?php
            $msgAuthor = $usersById[(int) $message['author_id']] ?? ['display_name' => 'Unknown'];
          ?>
          <?php $isMe = (int)$data['current_user'] !== 0 && (int)$message['author_id'] === (int)$data['current_user']; ?>
          <div class="chat-line<?= $isMe ? ' chat-line--me' : '' ?>" data-msg-id="<?= (int)$message['id'] ?>">
            <?php render_avatar($msgAuthor['display_name'] ?? 'Unknown', 28); ?>
            <div class="chat-body">
              <div class="chat-meta">
                <span class="chat-user"><?= htmlspecialchars($msgAuthor['display_name'] ?? 'Unknown') ?></span>
                <span class="chat-time"><?= htmlspecialchars($message['timestamp'] ?? '') ?></span>
              </div>
              <div class="chat-text"><?= htmlspecialchars($message['text'] ?? '') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if ($chatMayPost): ?>
    <div class="chat-composer">
      <textarea id="chat-input" placeholder="Write a message..." rows="1" aria-label="Write a message"></textarea>
      <button id="chat-send" class="chat-send" type="button" title="Send" aria-label="Send">
        <img class="chat-send-icon" src="https://nerd.biz/assets/fa/svgs/solid/paper-plane.svg" alt="">
      </button>
    </div>
    <?php elseif ($threadLocked && auth_is_logged_in()): ?>
    <div class="chat-composer chat-composer--locked">
      <textarea id="chat-input" placeholder="Chat locked" rows="1" aria-label="Chat locked" disabled></textarea>
      <button id="chat-send" class="chat-send" type="button" title="Chat locked" aria-label="Chat locked" disabled>
        <img class="chat-send-icon" src="https://nerd.biz/assets/fa/svgs/solid/lock.svg" alt="">
      </button>
    </div>
    <?php else: ?>
    <p class="chat-guest-note">Log in to chat.</p>
    <?php endif; ?>
  </section>
  </div>

  <div class="modal" id="post-modal" hidden>
    <div class="modal-scrim" data-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="post-modal-title" tabindex="-1">
      <button class="modal-close" type="button" aria-label="Close" data-close>
        <img class="post-action-icon" src="https://nerd.biz/assets/fa/svgs/solid/xmark.svg" width="16" height="16" alt="" aria-hidden="true">
      </button>
      <header class="modal-head">
        <?php render_avatar($postAuthorName, 40); ?>
        <div class="modal-byline">
          <span class="name"><?= htmlspecialchars($postAuthorName) ?></span>
          <span class="meta"><?= htmlspecialchars($postMeta) ?></span>
        </div>
      </header>
      <h2 id="post-modal-title" class="modal-title"><?= htmlspecialchars($thread['title'] ?? 'Thread') ?></h2>
      <div class="modal-body">
        <?= bbcode_to_html($body) ?>
      </div>
    </div>
  </div>
</main>
<?php
include __DIR__ . '/partials/footer.php';     // footer + scripts + </body></html>
