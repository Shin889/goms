<?php
// This is the main layout wrapper
// $page_title, $extra_css, $extra_js, and $content should be set before including this file
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'GOMS Admin'); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="../utils/css/admin_dashboard.css">
  <?php if (isset($extra_css) && is_array($extra_css)): ?>
    <?php foreach($extra_css as $css): ?>
      <link rel="stylesheet" href="<?= htmlspecialchars($css); ?>">
    <?php endforeach; ?>
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <?php include('includes/sidebar.php'); ?>
  
  <main class="content" id="mainContent">
    <?= $content ?? ''; ?>
  </main>
  
  <script src="../utils/js/sidebar.js"></script>
  <?php if (isset($extra_js) && is_array($extra_js)): ?>
    <?php foreach($extra_js as $js): ?>
      <script src="<?= htmlspecialchars($js); ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>