<footer class="bg-gray-800 text-white mt-12">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-xl font-bold mb-4"><?php echo getSiteName(); ?></h3>
                <p class="text-gray-400">Fast & secure file sharing platform</p>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Quick Links</h4>
                <ul class="space-y-2">
                    <li><a href="/terms" class="text-gray-400 hover:text-white">Terms of Service</a></li>
                    <li><a href="/privacy" class="text-gray-400 hover:text-white">Privacy Policy</a></li>
                    <li><a href="/dmca" class="text-gray-400 hover:text-white">DMCA</a></li>
                    <li><a href="/docs" class="text-gray-400 hover:text-white">Documentation</a></li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Contact</h4>
                <p class="text-gray-400">Email: <?php echo ADMIN_EMAIL; ?></p>
            </div>
        </div>
        
        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> <?php echo getSiteName(); ?>. All rights reserved.</p>
        </div>
    </div>
</footer>
