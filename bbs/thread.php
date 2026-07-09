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
      <h1 class="op-title"><?= htmlspecialchars($thread['title'] ?? 'Thread') ?></h1>
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
      <button class="post-action" type="button" data-action="share" aria-label="Share" title="Share">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="18" cy="5" r="3"></circle>
          <circle cx="6" cy="12" r="3"></circle>
          <circle cx="18" cy="19" r="3"></circle>
          <line x1="8.6" y1="13.5" x2="15.4" y2="17.5"></line>
          <line x1="15.4" y1="6.5" x2="8.6" y2="10.5"></line>
        </svg>
      </button>
      <button class="post-action" type="button" data-action="copy" aria-label="Copy link" title="Copy link">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"></path>
          <path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"></path>
        </svg>
      </button>
      <div class="reaction-wrap">
        <button class="post-action" type="button" data-action="react" aria-expanded="false" aria-haspopup="true" aria-label="React" title="React">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle>
            <line x1="9" y1="10" x2="9" y2="10"></line>
            <line x1="15" y1="10" x2="15" y2="10"></line>
            <path d="M8.5 14.5a4.5 4.5 0 0 0 7 0"></path>
          </svg>
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
    </div>
    <div class="post-reactions" aria-label="Reactions"></div>
  </article>

  <section class="chat" data-user-name="<?= htmlspecialchars($currentUserName) ?>" data-user-initials="<?= htmlspecialchars($currentUserInitials) ?>" data-user-id="<?= (int)$data['current_user'] ?>" data-thread-id="<?= (int)$threadId ?>" data-csrf="<?= htmlspecialchars(csrf_token()) ?>" data-can-post="<?= auth_is_logged_in() ? '1' : '' ?>" data-last-id="<?= (int)$lastChatId ?>">
    <div class="chat-header"><span>Live Chat</span></div>
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
    <?php if (auth_is_logged_in()): ?>
    <div class="chat-composer">
      <textarea id="chat-input" placeholder="Write a message..." rows="1" aria-label="Write a message"></textarea>
      <button id="chat-send" class="btn btn-primary" type="button">Send</button>
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
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
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
