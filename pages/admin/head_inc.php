<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
$siteFavicon = get_site_favicon();
$adminHeadBase = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? 'index.php'))), '/\\') . '/';
if ($siteFavicon !== ''):
?>
    <link rel="icon" href="<?php echo htmlspecialchars($adminHeadBase . ltrim($siteFavicon, '/'), ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
    <link rel="stylesheet" href="../../assets/css/tokens.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="stylesheet" href="../../assets/css/components.css?v=<?php echo ASSET_VERSION; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">