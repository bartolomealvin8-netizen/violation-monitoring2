<?php
declare(strict_types=1);

// Activate / deactivate (accounts are never deleted, so history stays intact).
if (is_post()) {
    csrf_verify();
    $targetId = (int)p('id');
    $target = user_by_id($targetId);
    if (!$target) {
        flash('error', 'That user no longer exists.');
    } elseif ($target['id'] === $user['id']) {
        flash('error', 'You cannot deactivate your own account.');
    } elseif ($target['active'] && $target['role'] === 'admin' && count_active_admins() <= 1) {
        flash('error', 'At least one active administrator is required.');
    } else {
        $newState = !$target['active'];
        db_mutate('users', function (array &$rows) use ($targetId, $newState) {
            foreach ($rows as &$r) {
                if ($r['id'] === $targetId) $r['active'] = $newState;
            }
        });
        flash('success', $target['name'] . ($newState ? ' was activated.' : ' was deactivated and can no longer sign in.'));
    }
    redirect('users');
}

$q = mb_strtolower(g('q'));
$role = g('role');
$state = g('state');
$sort = g('sort', 'name');
$dir = g('dir', 'asc') === 'desc' ? 'desc' : 'asc';

$all = db_read('users');
$rows = array_values(array_filter($all, function ($u) use ($q, $role, $state) {
    if ($role !== '' && $u['role'] !== $role) return false;
    if ($state === 'active' && !$u['active']) return false;
    if ($state === 'inactive' && $u['active']) return false;
    if ($q !== '' && !str_contains(mb_strtolower($u['name'] . ' ' . $u['email'] . ' ' . ($u['student_no'] ?? '')), $q)) return false;
    return true;
}));
$rows = sort_rows($rows, $sort, $dir, [
    'name'    => fn($u) => $u['name'],
    'role'    => fn($u) => ROLE_LABELS[$u['role']],
    'state'   => fn($u) => $u['active'] ? 0 : 1,
    'login'   => fn($u) => (string)($u['last_login'] ?? ''),
]);
[$pageRows, $meta] = paginate($rows);
$hasFilters = $q !== '' || $role !== '' || $state !== '';

layout_start('Users', 'Add people, change their roles and control who can sign in.',
    '<a class="btn btn-primary" href="' . e(url('user_form')) . '">' . icon('plus') . 'Add user</a>');
?>
<section class="card">
  <form method="get" class="filters">
    <input type="hidden" name="page" value="users">
    <div class="field grow">
      <label for="q" class="sr-only">Search</label>
      <div class="search"><?= icon('search') ?><input type="search" id="q" name="q" value="<?= e(g('q')) ?>" placeholder="Search by name, email or student number"></div>
    </div>
    <div class="field">
      <label for="role" class="sr-only">Role</label>
      <select id="role" name="role">
        <option value="">All roles</option>
        <?php foreach (ROLE_LABELS as $k => $label): ?><option value="<?= e($k) ?>" <?= $role === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="state" class="sr-only">Status</label>
      <select id="state" name="state">
        <option value="">Active and deactivated</option>
        <option value="active" <?= $state === 'active' ? 'selected' : '' ?>>Active only</option>
        <option value="inactive" <?= $state === 'inactive' ? 'selected' : '' ?>>Deactivated only</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <?php if ($hasFilters): ?><a class="btn btn-outline" href="<?= e(url('users')) ?>">Clear</a><?php endif; ?>
  </form>

  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr>
          <?= th_sort('Name', 'name', $sort, $dir) ?>
          <?= th_sort('Role', 'role', $sort, $dir) ?>
          <th>Department</th>
          <?= th_sort('Status', 'state', $sort, $dir) ?>
          <?= th_sort('Last sign-in', 'login', $sort, $dir) ?>
          <th><span class="sr-only">Actions</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($pageRows as $u): ?>
          <tr>
            <td><strong><?= e($u['name']) ?></strong><?= $u['id'] === $user['id'] ? ' <span class="badge badge-neutral">You</span>' : '' ?>
              <br><small class="muted"><?= e($u['email']) ?><?= !empty($u['student_no']) ? ' · ' . e($u['student_no']) : '' ?></small></td>
            <td><?= e(ROLE_LABELS[$u['role']]) ?></td>
            <td><?= e($u['department'] ?: '—') ?></td>
            <td><?= $u['active'] ? '<span class="badge badge-resolved">Active</span>' : '<span class="badge badge-neutral">Deactivated</span>' ?></td>
            <td><?= e($u['last_login'] ? fmt_datetime($u['last_login']) : 'Never') ?></td>
            <td class="t-right actions-cell">
              <a class="btn btn-outline btn-sm" href="<?= e(url('user_form', ['id' => $u['id']])) ?>">Edit</a>
              <?php if ($u['id'] !== $user['id']): ?>
                <form method="post" class="inline" data-confirm="<?= e($u['active']
                    ? 'Deactivate ' . $u['name'] . '? They will no longer be able to sign in. Their reports and history are kept.'
                    : 'Activate ' . $u['name'] . '? They will be able to sign in again.') ?>">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $u['active'] ? 'btn-danger' : 'btn-outline' ?>"><?= $u['active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager($meta) ?>
  <?php else: ?>
    <?= empty_state('No users found', 'Try a different search or clear the filters.', '<a class="btn btn-outline" href="' . e(url('users')) . '">Clear filters</a>') ?>
  <?php endif; ?>
</section>
<?php layout_end();
