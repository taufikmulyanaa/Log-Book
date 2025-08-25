<?php // templates/footer.php ?>
            </div>
        </main>
    </div>

    <script>
        // Vanilla JS for UI interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const menuButton = document.getElementById('menu-button');

            function toggleSidebar() {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            }

            if (menuButton) menuButton.addEventListener('click', toggleSidebar);
            if (overlay) overlay.addEventListener('click', toggleSidebar);

            // Profile menu toggle
            const profileButton = document.getElementById('profile-menu-button');
            const profileMenu = document.getElementById('profileMenu');

            if (profileButton && profileMenu) {
                profileButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    profileMenu.classList.toggle('hidden');
                });
            }

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                if (profileMenu && !profileMenu.classList.contains('hidden') && !profileButton.contains(event.target)) {
                    profileMenu.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>