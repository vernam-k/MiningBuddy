</main>
        
        <!-- Footer -->
        <footer class="bg-dark text-light border-top border-secondary py-4 mt-auto">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Mining Buddy</h5>
                        <p class="text-muted small">
                            A mining fleet management tool for EVE Online players.
                            Track mining operations, monitor profits, and optimize your fleet's efficiency.
                        </p>
                    </div>
                    <div class="col-md-3">
                        <h5>Links</h5>
                        <ul class="list-unstyled">
                            <li><a href="<?= APP_URL ?>" class="text-muted">Home</a></li>
                            <li><a href="<?= APP_URL ?>/dashboard.php" class="text-muted">Dashboard</a></li>
                            <li><a href="https://github.com/vernam-k/MiningBuddy" target="_blank" class="text-muted">GitHub</a></li>
                        </ul>
                    </div>
                    <div class="col-md-3">
                        <h5>Legal</h5>
                        <ul class="list-unstyled">
                            <li><a href="<?= APP_URL ?>/terms.php" class="text-muted">Terms of Service</a></li>
                            <li><a href="<?= APP_URL ?>/privacy.php" class="text-muted">Privacy Policy</a></li>
                        </ul>
                    </div>
                </div>
                <hr class="border-secondary">
                <div class="row">
                    <div class="col-md-6">
                        <p class="small text-muted mb-0">
                            &copy; <?= date('Y') ?> Mining Buddy
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="small text-muted mb-0">
                            EVE Online and all related logos and images are trademarks or registered trademarks of CCP hf.
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    
    <?php if (isset($extraScripts)): ?>
        <?= $extraScripts ?>
    <?php endif; ?>
</body>
</html>
<?php
// Flush the output buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>