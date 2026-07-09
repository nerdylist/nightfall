<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
require_login();
$data = require __DIR__ . '/data/live.php';
require_once __DIR__ . '/partials/avatar.php';

$me = auth_current_user();
$data['current_user'] = $me ? (int)$me['id'] : 0;

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main class="container py-7">
  <h1 class="mb-6">Settings</h1>

  <form action="#" method="post">

    <section class="settings-group">
      <h3>Edit Profile</h3>
      <p class="group-desc">Update how you appear across <?= htmlspecialchars($CONFIG['SITE_NAME']) ?>.</p>

      <div class="form-grid">
        <div class="field">
          <label for="display_name">Display name</label>
          <input type="text" id="display_name" name="display_name" placeholder="Display name" value="<?= htmlspecialchars($me['display_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($me['username'] ?? '') ?>">
        </div>

        <div class="field full-width">
          <label for="bio">Bio</label>
          <textarea id="bio" name="bio" placeholder="Tell the community a little about yourself..."><?= htmlspecialchars($me['bio'] ?? '') ?></textarea>
        </div>

        <div class="field full-width">
          <label>Avatar</label>
          <div class="flex gap-3">
            <div class="profile-avatar"><?php render_avatar($me['display_name'] ?? '', 120); ?></div>
          </div>
          <small>Avatars are generated automatically from your display name.</small>
        </div>
      </div>
    </section>

    <div class="settings-actions">
      <button type="button" class="btn btn-ghost">Cancel</button>
      <button type="submit" class="btn btn-primary">Save changes</button>
    </div>

  </form>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
