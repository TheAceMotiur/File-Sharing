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
        <h1 class="text-3xl font-bold mb-8">Terms of Service</h1>
        
        <div class="bg-white rounded-lg shadow p-8 prose max-w-none">
            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using <?php echo $siteName; ?>, you accept and agree to be bound by the terms and provision of this agreement.</p>
            
            <h2>2. Use License</h2>
            <p>Permission is granted to temporarily download one copy of the materials for personal, non-commercial transitory viewing only.</p>
            
            <h2>3. Disclaimer</h2>
            <p>The materials on <?php echo $siteName; ?>'s website are provided on an 'as is' basis. <?php echo $siteName; ?> makes no warranties, expressed or implied.</p>
            
            <h2>4. Limitations</h2>
            <p>In no event shall <?php echo $siteName; ?> or its suppliers be liable for any damages arising out of the use or inability to use the materials on <?php echo $siteName; ?>'s website.</p>
            
            <h2>5. Privacy</h2>
            <p>Your use of <?php echo $siteName; ?>'s website is also governed by our Privacy Policy.</p>
            
            <h2>6. Contact</h2>
            <p>If you have any questions about these Terms, please contact us at <?php echo ADMIN_EMAIL; ?>.</p>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
