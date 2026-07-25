<?php
/**
 * Keeper > Characters — authored catalog (partial of characters.php).
 * Expects: $keeperCsrf, $editChar (row|null), $catalogByType (type => rows[]),
 * $messagesByChar (character_id => message rows[]).
 * The add/edit form uses multipart for avatar/pose uploads. Each roster card
 * has a Messages button that opens that character's line editor in a modal.
 */
$cv = static function (string $k, $default = '') use ($editChar) {
    return htmlspecialchars((string) ($editChar[$k] ?? $default));
};
$isEdit = $editChar !== null;
$total = 0;
foreach (($catalogByType ?? []) as $rows) { $total += count($rows); }
?>

<div class="card keeper-table-card" id="character-form">
  <h2 class="keeper-table-card__heading"><?= $isEdit ? 'Edit Character' : 'Add Character' ?></h2>
  <p class="text-muted keeper-chars-hint">
    The game's characters — Humans, NPCs, and Zombies. This roster will feed the game's runtime startup as the list of characters running in the world.
  </p>

  <form method="post" action="/keeper/characters.php" enctype="multipart/form-data" class="keeper-chars-form">
    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $editChar['id'] ?>"><?php endif; ?>

    <div class="keeper-chars-grid">
      <input class="field keeper-chars-field--name" type="text" name="name" value="<?= $cv('name') ?>" placeholder="Name" required>
      <input class="field" type="number" name="age" value="<?= $cv('age') ?>" placeholder="Age" min="0">
      <select class="field" name="gender" aria-label="Gender">
        <?php foreach (['m' => 'Male', 'f' => 'Female', 'unknown' => 'Unknown'] as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= ($cv('gender', 'unknown') === $val) ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <select class="field" name="type" aria-label="Type">
        <?php foreach (['Human', 'NPC', 'Zombie'] as $t): ?>
        <option value="<?= $t ?>" <?= ($cv('type', 'Human') === $t) ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <textarea class="field keeper-chars-desc" name="description" rows="2" placeholder="Description (optional)"><?= $cv('description') ?></textarea>

    <div class="keeper-chars-uploads">
      <label class="keeper-chars-upload">
        <span class="keeper-chars-upload__label">Avatar photo</span>
        <?php if ($isEdit && !empty($editChar['avatar_path'])): ?>
          <img class="keeper-chars-upload__thumb" src="/<?= htmlspecialchars(ltrim((string) $editChar['avatar_path'], '/')) ?>" alt="">
        <?php endif; ?>
        <input class="field" type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif">
      </label>
      <label class="keeper-chars-upload">
        <span class="keeper-chars-upload__label">Pose photo</span>
        <?php if ($isEdit && !empty($editChar['pose_path'])): ?>
          <img class="keeper-chars-upload__thumb" src="/<?= htmlspecialchars(ltrim((string) $editChar['pose_path'], '/')) ?>" alt="">
        <?php endif; ?>
        <input class="field" type="file" name="pose" accept="image/png,image/jpeg,image/webp,image/gif">
      </label>
    </div>

    <div class="keeper-chars-actions">
      <button type="submit" name="save_character" value="1" class="btn btn-primary"><?= $isEdit ? 'Update Character' : 'Add Character' ?></button>
      <?php if ($isEdit): ?><a href="/keeper/characters.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card keeper-table-card">
  <h2 class="keeper-table-card__heading">Roster <span class="keeper-chars-count"><?= $total ?></span></h2>
  <?php if ($total === 0): ?>
    <p class="text-muted">No characters yet. Add one above.</p>
  <?php else: ?>
    <?php foreach (['Human', 'NPC', 'Zombie'] as $type): ?>
      <?php $rows = $catalogByType[$type] ?? []; if (!$rows) { continue; } ?>
      <div class="keeper-chars-group">
        <h3 class="keeper-chars-group__title"><?= htmlspecialchars($type) ?> <span class="keeper-chars-count"><?= count($rows) ?></span></h3>
        <div class="keeper-chars-cards">
          <?php foreach ($rows as $c): ?>
          <?php $cid = (int) $c['id']; $msgCount = count($messagesByChar[$cid] ?? []); $isActive = (int) $c['active'] === 1; ?>
          <div class="keeper-chars-card<?= $isActive ? '' : ' is-inactive' ?>">
            <form method="post" action="/keeper/characters.php" class="keeper-chars-toggle-form" title="<?= $isActive ? 'Active — in the runtime feed. Click to deactivate.' : 'Inactive — excluded from the runtime feed. Click to activate.' ?>">
              <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <button type="submit" name="toggle_active" value="1" class="keeper-chars-toggle<?= $isActive ? ' is-on' : '' ?>" aria-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>" role="switch" aria-checked="<?= $isActive ? 'true' : 'false' ?>">
                <span class="keeper-chars-toggle__knob"></span>
              </button>
            </form>
            <div class="keeper-chars-card__media">
              <?php if (!empty($c['avatar_path'])): ?>
                <img src="/<?= htmlspecialchars(ltrim((string) $c['avatar_path'], '/')) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
              <?php else: ?>
                <span class="keeper-chars-card__noimg"><?= htmlspecialchars(strtoupper(substr((string) $c['name'], 0, 1))) ?></span>
              <?php endif; ?>
            </div>
            <div class="keeper-chars-card__body">
              <span class="keeper-chars-card__name"><?= htmlspecialchars((string) $c['name']) ?></span>
              <span class="keeper-chars-card__meta">
                <?= htmlspecialchars((string) $c['type']) ?>
                <?php if ($c['age'] !== null && $c['age'] !== ''): ?> · <?= (int) $c['age'] ?><?php endif; ?>
                <?php if (!empty($c['gender']) && $c['gender'] !== 'unknown'): ?> · <?= strtoupper(htmlspecialchars((string) $c['gender'])) ?><?php endif; ?>
              </span>
            </div>
            <div class="keeper-chars-card__actions">
              <button type="button" class="keeper-icon-btn keeper-chars-msg-btn" data-open-messages="char-msg-<?= $cid ?>" title="Messages<?= $msgCount ? " ({$msgCount})" : '' ?>" aria-label="Messages">
                <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/comment-dots.svg" alt="">
                <?php if ($msgCount): ?><span class="keeper-chars-msg-count"><?= $msgCount ?></span><?php endif; ?>
              </button>
              <a class="keeper-icon-btn" href="/keeper/characters.php?edit=<?= $cid ?>#character-form" title="Edit" aria-label="Edit">
                <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/pen-to-square.svg" alt="">
              </a>
              <form method="post" action="/keeper/characters.php" onsubmit="return confirm('Delete <?= htmlspecialchars((string) $c['name'], ENT_QUOTES) ?>?');">
                <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <button type="submit" name="delete_character" value="1" class="keeper-icon-btn keeper-icon-btn--danger" title="Delete" aria-label="Delete">
                  <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/trash.svg" alt="">
                </button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php /* ---- Per-character Messages modals ---- */ ?>
<?php foreach (($catalog ?? []) as $c): ?>
<?php $cid = (int) $c['id']; $lines = $messagesByChar[$cid] ?? []; ?>
<div class="keeper-modal" id="char-msg-<?= $cid ?>" hidden>
  <div class="keeper-modal__backdrop" data-close-messages></div>
  <div class="keeper-modal__panel keeper-modal__panel--wide">
    <div class="keeper-modal__head">
      <h2 class="keeper-modal__title">Messages — <?= htmlspecialchars((string) $c['name']) ?></h2>
      <button type="button" class="keeper-modal__close" data-close-messages aria-label="Close">&times;</button>
    </div>
    <p class="text-muted keeper-chars-hint">Lines <?= htmlspecialchars((string) $c['name']) ?> might say in an overhead talk bubble. Uncheck <em>On</em> to keep a line but stop it being spoken. Clear a line's text to remove it.</p>

    <form method="post" action="/keeper/characters.php" class="keeper-chars-msgform">
      <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
      <input type="hidden" name="character_id" value="<?= $cid ?>">
      <div class="keeper-table-scroll">
        <table class="keeper-table keeper-messages-table">
          <thead>
            <tr><th>Line</th><th class="keeper-messages-col-on">On</th><th class="keeper-messages-col-del">Delete</th></tr>
          </thead>
          <tbody>
            <?php foreach ($lines as $line): ?>
            <tr>
              <td><input class="field" type="text" name="rows[<?= (int) $line['id'] ?>][body]" value="<?= htmlspecialchars((string) $line['body']) ?>" placeholder="What they say…"></td>
              <td class="keeper-messages-col-on"><input type="checkbox" name="rows[<?= (int) $line['id'] ?>][enabled]" value="1" <?= (int) $line['enabled'] === 1 ? 'checked' : '' ?> aria-label="Enabled"></td>
              <td class="keeper-messages-col-del"><input type="checkbox" name="rows[<?= (int) $line['id'] ?>][delete]" value="1" aria-label="Delete line"></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lines)): ?>
            <tr><td colspan="3" class="text-muted">No lines yet. Add one below.</td></tr>
            <?php endif; ?>
            <tr class="keeper-messages-new">
              <td><input class="field" type="text" name="rows[new][body]" value="" placeholder="Add a new line…"></td>
              <td class="keeper-messages-col-on"><input type="checkbox" name="rows[new][enabled]" value="1" checked aria-label="Enabled"></td>
              <td class="keeper-messages-col-del"><span class="text-muted">&mdash;</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="keeper-chars-actions keeper-modal__actions">
        <button type="submit" name="save_messages" value="1" class="btn btn-primary">Save Lines</button>
        <button type="button" class="btn btn-ghost" data-close-messages>Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
