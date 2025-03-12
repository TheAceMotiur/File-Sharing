<?php
/**
 * AdSense Ad Units Management File
 * This file provides functions for different ad placements throughout the site
 */

// Configuration - Update this with your actual AdSense information
$adsensePublisherId = 'ca-pub-9354746037074515'; // Your publisher ID
$adsenseEnabled = true; // Control global ads visibility

/**
 * Check if ads should be displayed based on various conditions
 * @return bool Whether ads should be displayed
 */
function shouldShowAds() {
    global $adsenseEnabled;
    
    // Don't show ads if globally disabled
    if (!$adsenseEnabled) return false;
    
    // If user is not logged in, show ads
    if (!isset($_SESSION['user_id'])) return true;
    
    // Check if user is premium (check both variables for compatibility)
    if ((isset($_SESSION['premium']) && $_SESSION['premium']) || 
        (isset($_SESSION['user_premium']) && $_SESSION['user_premium'])) {
        return false;
    }
    
    // If logged in but not premium, show ads
    return true;
}

/**
 * Display responsive horizontal ad banner
 */
function displayHorizontalAd() {
    global $adsensePublisherId;
    if (!shouldShowAds()) return;
    
    displayAdUnit([
        'container_class' => 'horizontal-ad my-6',
        'ad_format' => 'auto',
        'ad_slot' => '4878379783',
        'responsive' => true
    ]);
}

/**
 * Display responsive sidebar ad
 */
function displaySidebarAd() {
    global $adsensePublisherId;
    if (!shouldShowAds()) return;
    
    displayAdUnit([
        'container_class' => 'sidebar-ad mb-6',
        'ad_format' => 'auto',
        'ad_slot' => '4878379783',
        'responsive' => true
    ]);
}

/**
 * Display in-article ad
 */
function displayInArticleAd() {
    global $adsensePublisherId;
    if (!shouldShowAds()) return;
    
    displayAdUnit([
        'container_class' => 'in-article-ad my-8',
        'ad_layout' => 'in-article',
        'ad_format' => 'fluid',
        'ad_slot' => '4878379783',
        'text_align' => 'center'
    ]);
}

/**
 * Display homepage hero banner ad (premium placement)
 */
function displayHomepageHeroAd() {
    global $adsensePublisherId;
    if (!shouldShowAds()) return;
    
    displayAdUnit([
        'container_class' => 'homepage-hero-ad mt-4 mb-8',
        'ad_format' => 'horizontal',
        'ad_slot' => '4878379783',
        'responsive' => true,
        'label' => 'Sponsored'
    ]);
}

/**
 * Display homepage featured content ad
 */
function displayHomepageFeaturedAd() {
    global $adsensePublisherId;
    if (!shouldShowAds()) return;
    
    displayAdUnit([
        'container_class' => 'homepage-featured-ad my-10 max-w-4xl mx-auto',
        'ad_format' => 'rectangle',
        'ad_slot' => '4878379783',
        'responsive' => true,
        'enhanced' => true
    ]);
}

/**
 * General-purpose ad unit display function with various options
 * 
 * @param array $options Configuration options for the ad
 */
function displayAdUnit($options = []) {
    global $adsensePublisherId;
    
    // Set default options
    $defaults = [
        'container_class' => '',
        'ad_format' => 'auto',
        'ad_slot' => '4878379783',
        'responsive' => true,
        'ad_layout' => '',
        'text_align' => '',
        'label' => 'Advertisement',
        'enhanced' => false
    ];
    
    // Merge with provided options
    $opts = array_merge($defaults, $options);
    
    // Enhanced styles for premium placements
    $enhancedStyles = $opts['enhanced'] ? 'shadow-md hover:shadow-lg transition-shadow duration-300' : '';
    
    // Build the container classes
    $containerClass = "ad-container {$opts['container_class']} text-center bg-gray-50 py-3 px-2 rounded-lg border border-gray-100 {$enhancedStyles}";
    
    // Prepare ad attributes
    $adAttributes = [
        'style' => 'display:block' . ($opts['text_align'] ? '; text-align:' . $opts['text_align'] : ''),
        'data-ad-client' => $adsensePublisherId,
        'data-ad-slot' => $opts['ad_slot'],
        'data-ad-format' => $opts['ad_format']
    ];
    
    if ($opts['responsive']) {
        $adAttributes['data-full-width-responsive'] = 'true';
    }
    
    if ($opts['ad_layout']) {
        $adAttributes['data-ad-layout'] = $opts['ad_layout'];
    }
    
    // Generate HTML attributes string
    $attributesStr = '';
    foreach ($adAttributes as $key => $value) {
        $attributesStr .= " {$key}=\"{$value}\"";
    }
    
    ?>
    <div class="<?php echo $containerClass; ?>">
        <div class="text-xs text-gray-400 mb-1"><?php echo $opts['label']; ?></div>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?php echo $adsensePublisherId; ?>"
             crossorigin="anonymous"></script>
        <ins class="adsbygoogle"<?php echo $attributesStr; ?>></ins>
        <script>
             (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>
    <?php
}

/**
 * Display all relevant ads for the homepage
 * Places multiple ad units in strategic positions on the homepage
 */
function displayHomepageAds() {
    if (!shouldShowAds()) return;
    
    // Hero banner at the top
    displayHomepageHeroAd();
    
    // Featured content ad in the middle section
    displayHomepageFeaturedAd();
    
    // Additional horizontal ad at the bottom
    echo '<div class="homepage-bottom-ad mt-12">';
    displayHorizontalAd();
    echo '</div>';
    
    // Small sidebar-style ad in another section
    echo '<div class="homepage-side-section mt-8">';
    displaySidebarAd();
    echo '</div>';
}
?>
