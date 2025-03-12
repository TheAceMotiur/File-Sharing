<?php
/**
 * Google AdSense Code
 * This file contains the Google AdSense code to be included on all pages except download pages
 */

// Replace this with your actual Google AdSense code
function displayAdsense() {
    echo '<!-- Google AdSense -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9354746037074515"
     crossorigin="anonymous"></script>
<!-- OneNetly -->
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-9354746037074515"
     data-ad-slot="4878379783"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>
    <!-- End Google AdSense -->';
}
?>
