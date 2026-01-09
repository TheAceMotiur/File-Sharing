<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Privacy Policy</h1>
        
        <div class="bg-white rounded-lg shadow p-8 prose max-w-none">
            <h2>Information We Collect</h2>
            <p>We collect information you provide directly to us when you create an account, upload files, or contact us.</p>
            
            <h2>How We Use Your Information</h2>
            <p>We use the information we collect to provide, maintain, and improve our services.</p>
            
            <h2>Information Sharing</h2>
            <p>We do not share your personal information with third parties except as described in this policy.</p>
            
            <h2>Data Security</h2>
            <p>We take reasonable measures to help protect your personal information from loss, theft, misuse and unauthorized access.</p>
            
            <h2>Your Choices</h2>
            <p>You may update, correct, or delete your account information at any time by logging into your account.</p>
            
            <h2>Contact Us</h2>
            <p>If you have any questions about this Privacy Policy, please contact us at <?php echo ADMIN_EMAIL; ?>.</p>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
