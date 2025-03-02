<?php
require_once __DIR__ . '/config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMCA Policy - OneNetly</title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">DMCA Policy</h1>

        <div class="bg-white rounded-lg shadow-sm p-6 space-y-6">
            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">1. Digital Millennium Copyright Act</h2>
                <p class="text-gray-600">OneNetly respects the intellectual property rights of others and expects its users to do the same. In accordance with the Digital Millennium Copyright Act of 1998 ("DMCA"), we will respond expeditiously to claims of copyright infringement that are reported to our designated copyright agent.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">2. DMCA Notice Requirements</h2>
                <p class="text-gray-600 mb-4">If you are a copyright owner or authorized to act on behalf of one, you may submit a DMCA takedown notice if you believe that material on our service infringes your copyright. The notice must include:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>Physical or electronic signature of the copyright owner or authorized agent</li>
                    <li>Description of the copyrighted work claimed to be infringed</li>
                    <li>Description of the infringing material and its location</li>
                    <li>Your contact information (name, address, telephone number, email)</li>
                    <li>Statement of good faith belief that the use is not authorized</li>
                    <li>Statement that the information is accurate and penalty of perjury</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">3. Filing a DMCA Notice</h2>
                <p class="text-gray-600 mb-4">Send your DMCA notice to our designated copyright agent:</p>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-gray-600">Copyright Agent - OneNetly</p>
                    <p class="text-gray-600">Email: <?php echo htmlspecialchars($settings['contact_email'] ?? 'copyright@fileswith.com'); ?></p>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">4. Counter-Notice</h2>
                <p class="text-gray-600">If you believe your content was removed in error, you may submit a counter-notice containing:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>Your physical or electronic signature</li>
                    <li>Identification of the removed material and its former location</li>
                    <li>Statement under penalty of perjury that you believe removal was a mistake</li>
                    <li>Your consent to jurisdiction and contact information</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">5. Repeat Infringers</h2>
                <p class="text-gray-600">OneNetly maintains a policy of terminating accounts of users who are repeat copyright infringers. We reserve the right to terminate accounts of users who have multiple DMCA complaints filed against them.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">6. Modifications</h2>
                <p class="text-gray-600">OneNetly reserves the right to update this DMCA policy at any time. Users will be notified of any changes through our website.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">7. Contact Information</h2>
                <p class="text-gray-600">For any questions regarding this DMCA policy, please contact us at:</p>
                <p class="text-gray-600 mt-2">Email: <?php echo htmlspecialchars($settings['contact_email'] ?? 'support@fileswith.com'); ?></p>
            </section>
        </div>

        <div class="mt-8 text-center text-gray-600 text-sm">
            Last updated: <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>