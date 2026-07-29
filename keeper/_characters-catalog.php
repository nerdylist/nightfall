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
    The game's characters — Humans, NPCs, Zombies, and Enemies. This roster will feed the game's runtime startup as the list of characters running in the world.
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
        <?php foreach (['Human', 'NPC', 'Zombie', 'Enemy'] as $t): ?>
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

    <?php
      // Decode stored hp_bands JSON for pre-fill (edit). Malformed -> empty.
      $bands = [];
      if ($isEdit && !empty($editChar['hp_bands'])) {
          $decoded = json_decode((string) $editChar['hp_bands'], true);
          if (is_array($decoded)) { $bands = $decoded; }
      }
    ?>
    <?php
      // Archetype defaults for NEW enemy spawners: a fresh row is BORN with
      // dials (never dial-less) per the game's design. Zombie→SHAMBLER,
      // NPC/Enemy→HEAVY. Humans get nothing here — they level up separately.
      // Templates mirror the game's wave-dials-defaults.json.
      $archetypes = [
        'SHAMBLER' => ['hp_base'=>80,'wave_min'=>1,'hp_cap'=>3000,'xp_value'=>50,'hp_bands'=>[
          ['from'=>1,'to'=>10,'mode'=>'pct','value'=>3,'spawn_weight'=>100,'max_alive'=>8],
          ['from'=>11,'to'=>30,'mode'=>'pct','value'=>4,'spawn_weight'=>90,'max_alive'=>10],
          ['from'=>31,'to'=>60,'mode'=>'pct','value'=>2,'spawn_weight'=>70,'max_alive'=>12],
          ['from'=>61,'to'=>null,'mode'=>'pct','value'=>1,'spawn_weight'=>60,'max_alive'=>14],
        ]],
        'HEAVY' => ['hp_base'=>300,'wave_min'=>15,'hp_cap'=>12000,'xp_value'=>400,'hp_bands'=>[
          ['from'=>15,'to'=>30,'mode'=>'pct','value'=>8,'spawn_weight'=>5,'max_alive'=>1],
          ['from'=>31,'to'=>60,'mode'=>'pct','value'=>5,'spawn_weight'=>18,'max_alive'=>3],
          ['from'=>61,'to'=>100,'mode'=>'pct','value'=>2,'spawn_weight'=>30,'max_alive'=>5],
          ['from'=>101,'to'=>null,'mode'=>'pct','value'=>1,'spawn_weight'=>35,'max_alive'=>6],
        ]],
      ];
      $typeArchetype = ['Zombie'=>'SHAMBLER', 'NPC'=>'HEAVY', 'Enemy'=>'HEAVY']; // Human: none
    ?>
    <?php if (!$isEdit): ?>
    <script type="application/json" data-char-archetypes><?= json_encode(['archetypes'=>$archetypes,'byType'=>$typeArchetype], JSON_UNESCAPED_SLASHES) ?></script>
    <?php endif; ?>

    <fieldset class="keeper-chars-wave">
      <legend>Wave scaling <span class="keeper-chars-wave__opt">optional — spawner dials, read by the game at boot</span></legend>

      <div class="keeper-chars-wavegrid">
        <label class="keeper-chars-wavefield"><span>Base HP</span>
          <input class="field" type="number" name="hp_base" value="<?= $cv('hp_base') ?>" placeholder="game default" min="0"></label>
        <label class="keeper-chars-wavefield"><span>Wave min</span>
          <input class="field" type="number" name="wave_min" value="<?= $cv('wave_min') ?>" placeholder="1" min="1"></label>
        <label class="keeper-chars-wavefield"><span>Wave max</span>
          <input class="field" type="number" name="wave_max" value="<?= $cv('wave_max') ?>" placeholder="∞" min="1"></label>
        <label class="keeper-chars-wavefield"><span>HP cap</span>
          <input class="field" type="number" name="hp_cap" value="<?= $cv('hp_cap') ?>" placeholder="none" min="0"></label>
        <label class="keeper-chars-wavefield"><span>XP value</span>
          <input class="field" type="number" name="xp_value" value="<?= $cv('xp_value') ?>" placeholder="50" min="0" title="XP awarded for killing this character (absent → game default 50). Per-band overrides below."></label>
      </div>

      <div class="keeper-chars-bands" data-bands>
        <div class="keeper-chars-bands__head">
          <span>Bands</span>
          <span class="keeper-chars-wave__opt">per wave range: HP growth + spawn mix. Tile without gaps; first “From” = Wave min. Weight (pick likelihood, default 100) and Max (alive at once) are optional.</span>
        </div>
        <div class="keeper-chars-bands__rows" data-bands-rows>
          <?php
            // Render existing bands, plus always keep the markup for JS cloning
            // in a <template>. Each row: from / to / mode / value.
            $renderBand = function ($i, $b) {
                $from   = htmlspecialchars((string) ($b['from'] ?? ''));
                $to     = ($b['to'] ?? null) === null ? '' : htmlspecialchars((string) $b['to']);
                $mode   = ($b['mode'] ?? 'pct') === 'flat' ? 'flat' : 'pct';
                $val    = htmlspecialchars((string) ($b['value'] ?? ''));
                $weight = isset($b['spawn_weight']) ? htmlspecialchars((string) $b['spawn_weight']) : '';
                $alive  = isset($b['max_alive']) ? htmlspecialchars((string) $b['max_alive']) : '';
                $bxp    = isset($b['xp_value']) ? htmlspecialchars((string) $b['xp_value']) : '';
                ?>
                <div class="keeper-chars-band" data-band-row>
                  <input class="field" type="number" name="band_from[]" value="<?= $from ?>" placeholder="From" min="1">
                  <input class="field" type="number" name="band_to[]" value="<?= $to ?>" placeholder="To (∞)" min="1">
                  <select class="field" name="band_mode[]" aria-label="Mode">
                    <option value="pct"  <?= $mode === 'pct'  ? 'selected' : '' ?>>% / wave</option>
                    <option value="flat" <?= $mode === 'flat' ? 'selected' : '' ?>>flat / wave</option>
                  </select>
                  <input class="field" type="number" name="band_value[]" value="<?= $val ?>" placeholder="Value" step="any">
                  <input class="field" type="number" name="band_spawn_weight[]" value="<?= $weight ?>" placeholder="Weight" min="0" title="Spawn weight — relative pick likelihood in this range (default 100)">
                  <input class="field" type="number" name="band_max_alive[]" value="<?= $alive ?>" placeholder="Max" min="1" title="Max alive — ceiling of this character alive at once in this range">
                  <input class="field" type="number" name="band_xp_value[]" value="<?= $bxp ?>" placeholder="XP" min="0" title="XP value — XP for a kill in this wave range (overrides the character default)">
                  <button type="button" class="keeper-icon-btn keeper-icon-btn--danger" data-band-remove title="Remove band" aria-label="Remove band">
                    <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/xmark.svg" alt="">
                  </button>
                </div>
                <?php
            };
            foreach ($bands as $i => $b) { $renderBand($i, $b); }
          ?>
        </div>
        <template data-band-template>
          <?php $renderBand('__i__', []); ?>
        </template>
        <button type="button" class="btn btn-ghost keeper-chars-band-add" data-band-add>+ Add band</button>
      </div>
    </fieldset>

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
    <?php foreach (['Human', 'NPC', 'Zombie', 'Enemy'] as $type): ?>
      <?php $rows = $catalogByType[$type] ?? []; if (!$rows) { continue; } ?>
      <div class="keeper-chars-group">
        <h3 class="keeper-chars-group__title"><?= htmlspecialchars($type) ?> <span class="keeper-chars-count"><?= count($rows) ?></span></h3>
        <div class="keeper-chars-cards">
          <?php foreach ($rows as $c): ?>
          <?php $cid = (int) $c['id']; $msgCount = count($messagesByChar[$cid] ?? []); $isActive = (int) $c['active'] === 1; ?>
          <div class="keeper-chars-card<?= $isActive ? '' : ' is-inactive' ?>">
            <div class="keeper-chars-card__media">
              <?php if (!empty($c['avatar_path'])): ?>
                <img src="/<?= htmlspecialchars(ltrim((string) $c['avatar_path'], '/')) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
              <?php else: ?>
                <span class="keeper-chars-card__noimg"><?= htmlspecialchars(strtoupper(substr((string) $c['name'], 0, 1))) ?></span>
              <?php endif; ?>
              <form method="post" action="/keeper/characters.php" class="keeper-chars-toggle-form" title="<?= $isActive ? 'Active — in the runtime feed. Click to deactivate.' : 'Inactive — excluded from the runtime feed. Click to activate.' ?>">
                <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <button type="submit" name="toggle_active" value="1" class="keeper-chars-toggle<?= $isActive ? ' is-on' : '' ?>" aria-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>" role="switch" aria-checked="<?= $isActive ? 'true' : 'false' ?>">
                  <span class="keeper-chars-toggle__knob"></span>
                </button>
              </form>
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
