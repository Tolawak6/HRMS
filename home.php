<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$publicTitle = 'Human Resource Management System';
$publicActive = 'home';
include BASE_PATH . '/includes/layout/public_header.php';
?>
<main>
    <section class="home-hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <p class="eyebrow">Ethiopian Statistical Service</p>
                    <h1>Human Resource Management System</h1>
                    <p class="hero-accent">
                        A centralized, secure record of ESS employees — personnel files, organizational structure,
                        leave administration, documents and workforce reporting in one place.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-light btn-lg" href="<?= e(url('login.php')) ?>"><?= icon('box-arrow-in-right') ?> Staff sign in</a>
                        <a class="btn btn-outline-light btn-lg" href="<?= e(url('about_us.php')) ?>">About ESS</a>
                    </div>
                    <p class="tiny mt-3 mb-0" style="color:#a9bdd2">
                        Access is restricted to authorized personnel. Every sign-in and account action is logged.
                    </p>
                </div>
                <div class="col-lg-5">
                    <div class="home-hero-card">
                        <h2>What the system covers</h2>
                        <div class="feature"><?= icon('people') ?><div><strong>Employee records</strong><span>Profiles, employment history, contacts and photographs.</span></div></div>
                        <div class="feature"><?= icon('building') ?><div><strong>Organization</strong><span>Departments, positions and reporting structure.</span></div></div>
                        <div class="feature"><?= icon('calendar2-check') ?><div><strong>Leave administration</strong><span>Entitlements, requests, approvals and balances.</span></div></div>
                        <div class="feature"><?= icon('folder2-open') ?><div><strong>Documents</strong><span>Personnel files stored outside the public web directory.</span></div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <p class="eyebrow text-uppercase" style="color: var(--ess-muted-2); font-size: .6875rem; letter-spacing: .12em;">Purpose</p>
                    <h2>Built around the employee record</h2>
                </div>
                <div class="col-lg-8">
                    <p class="lead-2">
                        The system replaces scattered spreadsheets and paper files with a single, auditable personnel
                        record. Human Resources staff manage employee data, departments, positions and documents;
                        employees use self-service to view their own record, request leave and receive notifications.
                    </p>
                </div>
            </div>
            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <div class="home-feature">
                        <div class="icon"><?= icon('shield-check') ?></div>
                        <h3>Role-based access</h3>
                        <p>Administrators, HR staff and employees see only what their role permits. Permissions are enforced on the server.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="home-feature">
                        <div class="icon"><?= icon('journal-text') ?></div>
                        <h3>Audit trail</h3>
                        <p>Account, employee, leave, document and reporting actions are recorded with actor, time and source address.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="home-feature">
                        <div class="icon"><?= icon('bar-chart') ?></div>
                        <h3>Evidence for decisions</h3>
                        <p>Headcount, department distribution, workforce profile and leave reports are calculated from live records.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section is-tinted">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <h2>Access and responsibilities</h2>
                    <p class="lead-2 mb-3">
                        Accounts are issued by Human Resources. Employees receive a temporary password and must set
                        their own password at first sign-in. Lost credentials are reissued by HR — never shared by email.
                    </p>
                    <ul class="stack-sm text-muted ps-3 mb-0">
                        <li>Use the system only for authorized HR purposes.</li>
                        <li>Personal data must be handled in line with ESS policy and applicable law.</li>
                        <li>Report suspected unauthorized access to Human Resources immediately.</li>
                    </ul>
                </div>
                <div class="col-lg-5">
                    <div class="panel">
                        <div class="panel-header"><div><h2>Need access?</h2><div class="meta">Contact Human Resources</div></div></div>
                        <div class="panel-body">
                            <div class="mini-list">
                                <div class="row-line"><span>Email</span><span>info@ess.gov.et</span></div>
                                <div class="row-line"><span>Phone</span><span>+251-11-1234567</span></div>
                                <div class="row-line"><span>Location</span><span>Addis Ababa, Ethiopia</span></div>
                            </div>
                            <div class="divider"></div>
                            <a class="btn btn-primary w-100" href="<?= e(url('login.php')) ?>"><?= icon('box-arrow-in-right') ?> Go to sign in</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include BASE_PATH . '/includes/layout/public_footer.php'; ?>
