<?php
require_once '../config.php';
$db = getDBConnection();

// Get base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseURL = $protocol . $_SERVER['HTTP_HOST'];

// Define media types
$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$videoTypes = ['mp4', 'webm', 'mov', 'avi'];

// Generate static pages sitemap (sitemap0.xml)
$staticPages = [
    '' => ['priority' => '1.0', 'changefreq' => 'daily', 'section' => 'main'],
    'login.php' => ['priority' => '0.8', 'changefreq' => 'monthly', 'section' => 'auth'],
    'register.php' => ['priority' => '0.8', 'changefreq' => 'monthly', 'section' => 'auth'],
    'terms.php' => ['priority' => '0.7', 'changefreq' => 'monthly', 'section' => 'legal'],
    'privacy.php' => ['priority' => '0.7', 'changefreq' => 'monthly', 'section' => 'legal'],
    'docs.php' => ['priority' => '0.9', 'changefreq' => 'weekly', 'section' => 'documentation'],
    'dmca.php' => ['priority' => '0.7', 'changefreq' => 'monthly', 'section' => 'legal']
];

generateStaticSitemap($staticPages, $baseURL);

// Generate file sitemaps
$offset = 0;
$sitemapIndex = 1;

while(true) {
    $query = "SELECT 
                file_id,
                file_name,
                size,
                created_at,
                last_download_at,
                LOWER(SUBSTRING_INDEX(file_name, '.', -1)) as extension
              FROM file_uploads 
              WHERE upload_status = 'completed' 
              AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
              ORDER BY created_at DESC
              LIMIT 500 OFFSET $offset";
              
    $result = $db->query($query);
    
    if ($result->num_rows == 0) {
        break;
    }
    
    generateFileSitemap($result, $sitemapIndex, $baseURL, $imageTypes, $videoTypes);
    
    $offset += 500;
    $sitemapIndex++;
}

function generateStaticSitemap($pages, $baseURL) {
    $xml = generateXMLHeader();
    
    foreach ($pages as $page => $metadata) {
        $xml .= generateUrlEntry(
            $baseURL . '/' . $page,
            date('c'),
            $metadata['changefreq'],
            $metadata['priority'],
            $page === '' ? "$baseURL/icon.png" : null
        );
    }
    
    $xml .= "</urlset>";
    file_put_contents(__DIR__ . '/sitemap0.xml', $xml);
}

function generateFileSitemap($result, $index, $baseURL, $imageTypes, $videoTypes) {
    $xml = generateXMLHeader();
    
    while ($file = $result->fetch_assoc()) {
        $lastmod = $file['last_download_at'] ?? $file['created_at'];
        $downloadUrl = $baseURL . '/download/' . $file['file_id'];
        
        $xml .= generateUrlEntry(
            $downloadUrl,
            date('c', strtotime($lastmod)),
            'weekly',
            '0.6',
            in_array($file['extension'], $imageTypes) ? "$downloadUrl/download" : null,
            in_array($file['extension'], $videoTypes) ? $file : null
        );
    }
    
    $xml .= "</urlset>";
    file_put_contents(__DIR__ . "/sitemap$index.xml", $xml);
}

function generateXMLHeader() {
    return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
           '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
                   xmlns:xhtml="http://www.w3.org/1999/xhtml"
                   xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
                   xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . PHP_EOL;
}

function generateUrlEntry($loc, $lastmod, $changefreq, $priority, $imageUrl = null, $videoFile = null) {
    $xml = "    <url>\n";
    $xml .= "        <loc>" . htmlspecialchars($loc) . "</loc>\n";
    $xml .= "        <lastmod>$lastmod</lastmod>\n";
    $xml .= "        <changefreq>$changefreq</changefreq>\n";
    $xml .= "        <priority>$priority</priority>\n";
    
    if ($imageUrl) {
        $xml .= "        <image:image>\n";
        $xml .= "            <image:loc>" . htmlspecialchars($imageUrl) . "</image:loc>\n";
        $xml .= "            <image:title>FreeNetly</image:title>\n";
        $xml .= "        </image:image>\n";
    }
    
    if ($videoFile) {
        $xml .= "        <video:video>\n";
        $xml .= "            <video:title>" . htmlspecialchars($videoFile['file_name']) . "</video:title>\n";
        $xml .= "            <video:description>Download " . htmlspecialchars($videoFile['file_name']) . " via FreeNetly</video:description>\n";
        $xml .= "            <video:content_loc>" . htmlspecialchars($loc) . "/download</video:content_loc>\n";
        $xml .= "            <video:thumbnail_loc>https://freenetly.com/icon.png</video:thumbnail_loc>\n";
        $xml .= "        </video:video>\n";
    }
    
    $xml .= "    </url>\n";
    return $xml;
}