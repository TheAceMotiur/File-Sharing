<?php
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;

require_once 'config.php';
$db = getDBConnection();

// Get base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseURL = $protocol . $_SERVER['HTTP_HOST'];

// Count total files
$countQuery = "SELECT COUNT(*) as total FROM file_uploads 
               WHERE upload_status = 'completed' 
               AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)";
$totalFiles = $db->query($countQuery)->fetch_assoc()['total'];

// Calculate number of sitemaps needed (500 per file + 1 for static pages)
$totalSitemaps = ceil($totalFiles / 500) + 1;
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php for($i = 0; $i < $totalSitemaps; $i++): ?>
    <sitemap>
        <loc><?php echo $baseURL; ?>/sitemaps/sitemap<?php echo $i; ?>.xml</loc>
        <lastmod><?php echo date('c'); ?></lastmod>
    </sitemap>
    <?php endfor; ?>
</sitemapindex>