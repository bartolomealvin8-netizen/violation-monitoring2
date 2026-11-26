<?php
declare(strict_types=1);

$lists = [
    'departments'     => ['title' => 'Departments', 'singular' => 'department', 'hint' => 'Used for students, deans and reports.'],
    'violation_types' => ['title' => 'Violation types', 'singular' => 'violation type', 'hint' => 'Shown in the report form.'],
];

/** Counts how many records use a value, so used values cannot be removed. */
function setting_usage(string $list, string $value): int
{
    $n = 0;
    if ($list === 'departments') {
        foreach (db_read('users') as $u) if (($u['department'] ?? '') === $value) $n++;
        foreach (db_read('cases') as $c) if ($c['department'] === $value) $n++;
    } else {
        foreach (db_read('cases') as $c) if ($c['type'] === $value) $n++;
    }
    return $n;
}

$errors = [];
$old = [];

if (is_post()) {
    csrf_verify();
    $list = p('list');
    $action = p('action');
    $value = p('value');
    if (!isset($lists[$list])) {
        redirect('settings');
    }
    $singular = $lists[$list]['singular'];
    $current = settings()[$list];

    if ($action === 'add') {
        $old[$list] = $value;
        if ($value === '' || mb_strlen($value) > 80) {
            $errors[$list] = 'Enter a name (up to 80 characters).';
        } elseif (in_array(mb_strtolower($value), array_map('mb_strtolower', $current), true)) {
            $errors[$list] = 'This ' . $singular . ' already exists.';
        } else {
            db_mutate('settings', function (array &$s) use ($list, $value) {
                $s[$list][] = $value;
            });
            flash('success', ucfirst($singular) . ' “' . $value . '” was added.');
            redirect('settings');
        }
    } elseif ($action === 'remove') {
        if (!in_array($value, $current, true)) {
            flash('error', 'That item no longer exists.');
        } elseif (setting_usage($list, $value) > 0) {
            flash('error', '“' . $value . '” is in use and cannot be removed.');
        } else {
            db_mutate('settings', function (array &$s) use ($list, $value) {
                $s[$list] = array_values(array_filter($s[$list], fn($x) => $x !== $value));
            });
            flash('success', ucfirst($singular) . ' “' . $value . '” was removed.');
        }
        redirect('settings');
    }
}

$settings = settings();
layout_start('System Settings', 'Manage the lists used across the system.');
if ($errors) {
    echo '<div class="alert alert-error" role="alert">' . icon('alert') . '<span>Please fix the highlighted field and try again.</span></div>';
}
?>
<div class="split split-even">
<?php foreach ($lists as $key => $meta): ?>
  <section class="card">
    <h2><?= e($meta['title']) ?></h2>
    <p class="muted"><?= e($meta['hint']) ?></p>
    <form method="post" class="inline-add" novalidate>
      <?= csrf_field() ?><input type="hidden" name="list" value="<?= e($key) ?>"><input type="hidden" name="action" value="add">
      <div class="field grow">
        <label for="add-<?= e($key) ?>" class="sr-only">New <?= e($meta['singular']) ?></label>
        <input type="text" id="add-<?= e($key) ?>" name="value" value="<?= e($old[$key] ?? '') ?>" placeholder="New <?= e($meta['singular']) ?>">
        <?= field_error($errors, $key) ?>
      </div>
      <button type="submit" class="btn btn-primary">Add</button>
    </form>
    <?php if ($settings[$key]): ?>
      <ul class="rows">
        <?php foreach ($settings[$key] as $item): $used = setting_usage($key, $item); ?>
          <li>
            <span><?= e($item) ?><?php if ($used): ?> <small class="muted">· in use (<?= $used ?>)</small><?php endif; ?></span>
            <?php if ($used): ?>
              <span class="muted small">Cannot remove</span>
            <?php else: ?>
              <form method="post" class="inline" data-confirm="Remove “<?= e($item) ?>”? This cannot be undone.">
                <?= csrf_field() ?><input type="hidden" name="list" value="<?= e($key) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="value" value="<?= e($item) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?= empty_state('Nothing here yet', 'Add the first ' . $meta['singular'] . ' above.') ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<?php layout_end();
