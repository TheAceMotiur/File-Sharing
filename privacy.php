<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ads.php'; // Include ads functionality
session_start();
require_once __DIR__ . '/includes/auth.php';

// Update premium status in session to ensure it's current
if (isset($_SESSION['user_id'])) {
    updatePremiumStatus($_SESSION['user_id'], getDBConnection());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Privacy Policy</h1>

        <?php displayHorizontalAd(); // Display horizontal ad at top of content ?>

        <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">1. Information We Collect</h2>
                <div class="space-y-4 text-gray-600">
                    <p>We collect information that you provide directly to us, including:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Name and email address when you create an account</li>
                        <li>Files you upload to our service</li>
                        <li>Usage data and access logs</li>
                        <li>Device and browser information</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">2. How We Use Your Information</h2>
                <div class="space-y-4 text-gray-600">
                    <p>We use the information we collect to:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Provide and maintain our service</li>
                        <li>Process your file uploads and downloads</li>
                        <li>Send you service-related notifications</li>
                        <li>Improve and optimize our service</li>
                        <li>Detect and prevent abuse</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">3. Data Storage and Security</h2>
                <div class="space-y-4 text-gray-600">
                    <p>We implement appropriate security measures to protect your information:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Files are stored securely using enterprise cloud storage</li>
                        <li>Data is encrypted in transit and at rest</li>
                        <li>Regular security audits and updates</li>
                        <li>Limited staff access to user data</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">4. Data Retention</h2>
                <div class="space-y-4 text-gray-600">
                    <p>We retain your information as follows:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Account information is retained while your account is active</li>
                        <li>Usage logs are kept for 30 days</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">5. Your Rights</h2>
                <div class="space-y-4 text-gray-600">
                    <p>You have the right to:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Access your personal data</li>
                        <li>Correct inaccurate data</li>
                        <li>Request deletion of your data</li>
                        <li>Export your data</li>
                        <li>Opt-out of marketing communications</li>
                    </ul>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">6. Cookies</h2>
                <div class="space-y-4 text-gray-600">
                    <p>We use cookies to:</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Maintain your session</li>
                        <li>Remember your preferences</li>
                        <li>Understand how you use our service</li>
                    </ul>
                </div>
            </section>

            <?php displayInArticleAd(); // Display in-article ad mid-content ?>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">7. Contact Us</h2>
                <p class="text-gray-600">If you have any questions about this Privacy Policy, please contact us at:</p>
                <p class="text-gray-600 mt-2">Email: <?php echo htmlspecialchars($settings['contact_email'] ?? 'support@fileswith.com'); ?></p>
            </section>
        </div>

        <div class="mt-8">
            <?php displayHomepageFeaturedAd(); // Display featured ad at bottom ?>
        </div>
        
        <div class="mt-8 text-center text-gray-600 text-sm">
            Last updated: <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>