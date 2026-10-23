    <footer class="public-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="<?= e(url('images/logo.png')) ?>" alt="" width="34" height="29">
                        <strong class="text-white small">Ethiopian Statistical Service</strong>
                    </div>
                    <p class="mb-2">Human Resource Management System for employee records, organizational structure, documents and leave administration.</p>
                    <p class="mb-0">Addis Ababa, Ethiopia</p>
                </div>
                <div class="col-6 col-lg-3">
                    <h3>Institution</h3>
                    <ul class="list-unstyled mb-0 stack-sm">
                        <li><a href="<?= e(url('home.php')) ?>">Home</a></li>
                        <li><a href="<?= e(url('about_us.php')) ?>">About ESS</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-4">
                    <h3>System access</h3>
                    <ul class="list-unstyled mb-0 stack-sm">
                        <li><a href="<?= e(url('login.php')) ?>">Staff sign in</a></li>
                        <li>Access is restricted to authorized personnel.</li>
                        <li>Security events and account activity are logged.</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2">
                <span>© <?= date('Y') ?> Ethiopian Statistical Service. Internal HR system.</span>
                <span>Authorized personnel only</span>
            </div>
        </div>
    </footer>
    <script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
</body>
</html>
