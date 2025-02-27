<?php
require_once __DIR__ . '/../config.php';
$db = getDBConnection();

// Get local URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseURL = $protocol . $_SERVER['HTTP_HOST'];

try {
    // Generate sitemaps using the local script
    require_once __DIR__ . '/../cron/generate.php';
    
    // Generate main sitemap index
    $sitemapContent = file_get_contents($baseURL . '/sitemap.php');
    
    if ($sitemapContent === false) {
        throw new Exception("Failed to fetch sitemap content");
    }

    // Create cache directory if needed
    $cachePath = __DIR__ . '/../cache/sitemap.xml';
    if (!is_dir(dirname($cachePath))) {
        mkdir(dirname($cachePath), 0755, true);
    }

    // Save sitemap index
    if (file_put_contents($cachePath, $sitemapContent) === false) {
        throw new Exception("Failed to write sitemap cache");
    }

    chmod($cachePath, 0644);
    echo "Sitemap generated successfully\n";

} catch (Exception $e) {
    error_log("Sitemap generation failed: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}