<?php if (!empty($_SESSION['admin_flash'])):
  $flash = $_SESSION['admin_flash'];
  $suffix = ($flash['type'] === 'success') ? 'success' : 'error';
?>
<div class="admin-flash admin-flash--<?= $suffix ?>"><?= adm_e($flash['msg']) ?></div>
<?php unset($_SESSION['admin_flash']); endif; ?>