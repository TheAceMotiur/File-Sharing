<?php
require_once __DIR__ . '/config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - OneNetly</title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Terms of Service</h1>

        <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">1. Acceptance of Terms</h2>
                <p class="text-gray-600">By accessing and using OneNetly, you accept and agree to be bound by the terms and provision of this agreement.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">2. File Sharing Rules</h2>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>Users must not upload files that infringe on intellectual property rights</li>
                    <li>Prohibited content includes malware, illegal content, and harmful material</li>
                    <li>Files are automatically removed after 180 days of inactivity</li>
                    <li>Maximum file size is limited to 100MB per upload</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">3. User Responsibilities</h2>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>Users are responsible for the content they upload</li>
                    <li>Users must not share access credentials</li>
                    <li>Users must report any violations or abuse</li>
                    <li>Users must comply with DMCA takedown requests</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">4. Service Limitations</h2>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>Service availability is not guaranteed</li>
                    <li>Files may be removed without notice if they violate our terms</li>
                    <li>We reserve the right to modify or terminate the service at any time</li>
                    <li>Bandwidth limitations may apply</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">5. DMCA Policy</h2>
                <p class="text-gray-600">We respond to DMCA notices and terminate accounts of repeat infringers. See our <a href="dmca" class="text-blue-600 hover:underline">DMCA Policy</a> for more information.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">6. Privacy</h2>
                <p class="text-gray-600">Your use of OneNetly is also governed by our <a href="privacy" class="text-blue-600 hover:underline">Privacy Policy</a>.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">7. Modifications</h2>
                <p class="text-gray-600">We reserve the right to modify these terms at any time. Continued use of the service constitutes acceptance of modified terms.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">8. Contact</h2>
                <p class="text-gray-600">For any questions regarding these terms, please contact our support team.</p>
            </section>
        </div>

        <div class="mt-8 text-center text-gray-600 text-sm">
            Last updated: <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>