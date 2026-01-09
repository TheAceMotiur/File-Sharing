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
        <h1 class="text-3xl font-bold mb-8">DMCA Policy</h1>
        
        <div class="bg-white rounded-lg shadow p-8 prose max-w-none">
            <h2>Digital Millennium Copyright Act Notice</h2>
            <p><?php echo $siteName; ?> respects the intellectual property rights of others and expects its users to do the same.</p>
            
            <h2>Filing a DMCA Notice</h2>
            <p>If you believe that your work has been copied in a way that constitutes copyright infringement, please provide our designated agent with the following information:</p>
            
            <ul>
                <li>An electronic or physical signature of the person authorized to act on behalf of the owner of the copyright interest</li>
                <li>A description of the copyrighted work that you claim has been infringed</li>
                <li>A description of where the material that you claim is infringing is located on the site</li>
                <li>Your address, telephone number, and email address</li>
                <li>A statement by you that you have a good faith belief that the disputed use is not authorized</li>
                <li>A statement by you that the above information is accurate</li>
            </ul>
            
            <h2>Contact Information</h2>
            <p>Please send DMCA notices to: <?php echo ADMIN_EMAIL; ?></p>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
