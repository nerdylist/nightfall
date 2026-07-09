<?php $active = $active ?? ''; ?>
<nav class="admin-nav">
  <div class="container">
    <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="/bbs/admin/index.php">Dashboard</a>
    <a class="<?= $active === 'users' ? 'active' : '' ?>" href="/bbs/admin/users.php">Users</a>
    <a class="<?= $active === 'categories' ? 'active' : '' ?>" href="/bbs/admin/categories.php">Categories</a>
    <a class="<?= $active === 'threads' ? 'active' : '' ?>" href="/bbs/admin/threads.php">Threads</a>
    <a class="<?= $active === 'chat' ? 'active' : '' ?>" href="/bbs/admin/chat.php">Chat</a>
  </div>
</nav>