<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$publicTitle = 'About ESS';
$publicActive = 'about';
include BASE_PATH . '/includes/layout/public_header.php';
?>
<main>
    <section class="about-hero">
        <div class="container">
            <p class="eyebrow">Ethiopian Statistical Service</p>
            <h1>About the institution</h1>
            <p>The ESS leads the national statistical system, producing reliable official statistics for evidence-based policy and decision-making.</p>
        </div>
    </section>

    <section class="about-content">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <h2>Introduction</h2>
                    <p>
                        Statistical data collected and compiled using scientific methodologies are crucial for accelerating
                        the economic, social and democratic activities of a country, and for enhancing people's lives.
                        These data enable the Government and other institutions to formulate policies for various sectors,
                        monitor and evaluate policy implementations, and make informed decisions.
                    </p>
                    <p>
                        The Ethiopian Statistical Service (ESS) collects and compiles data from sample surveys, censuses and
                        administrative records, analyses the data and delivers reports for various sectors and users. The ESS
                        also provides technical guidance and assistance to government agencies, institutions and individuals
                        to help them establish administrative recording, registration and reporting systems.
                    </p>
                    <p>
                        To maintain internationally accepted standards and satisfy the demand for statistical data, the ESS
                        is diversifying its statistical methodologies and upgrading the use of technology in data collection,
                        compilation, classification, analysis and dissemination.
                    </p>

                    <h2>Background</h2>
                    <p>
                        Although statistical work in Ethiopia started many years ago, an organized system was created in the
                        late 1950s, in part because statistical data was needed for the development of socioeconomic plans.
                        In 1952 a conference of African statisticians held under the African Economic Commission agreed that
                        participating countries should submit a five-year statistical action plan, which encouraged the
                        creation of a more organized statistical system in Ethiopia.
                    </p>
                    <p>
                        Proclamations in 2006 and 2007, renewing the Central Statistics Authority and establishing the
                        Population Census Commission respectively, were fundamental in creating the statistical system ESS
                        has today. The office was named the Central Statistics Agency in 2007. ESS was established in 1959
                        under the instruction of the Minister of Trade and Industry with 14 professional staff members.
                    </p>

                    <div class="vision-mission">
                        <h2>Vision</h2>
                        <p>“To become globally competent and the center of excellence for reliable official statistical data.”</p>
                        <h2>Mission</h2>
                        <p>
                            “Our mission is generating, analyzing, and disseminating reliable statistical data timely and
                            appropriately to the users by establishing integrated systems and undertaking standardized
                            scientific methods and modern technologies transparently and professionally.”
                        </p>
                    </div>

                    <h2>About this system</h2>
                    <p>
                        The ESS Human Resource Management System supports the institution's personnel administration:
                        employee records, organizational structure, leave, personnel documents, notifications and workforce
                        reporting. It is an internal system; accounts are issued and managed by Human Resources.
                    </p>
                </div>

                <div class="col-lg-4">
                    <div class="panel">
                        <div class="panel-header"><div><h2>Contact</h2><div class="meta">Ethiopian Statistical Service</div></div></div>
                        <div class="panel-body">
                            <div class="mini-list">
                                <div class="row-line"><span>Email</span><span>info@ess.gov.et</span></div>
                                <div class="row-line"><span>Phone</span><span>+251-11-1234567</span></div>
                                <div class="row-line"><span>Location</span><span>Addis Ababa, Ethiopia</span></div>
                            </div>
                            <div class="divider"></div>
                            <p class="tiny text-muted-2 mb-3">
                                For HRMS account support, contact the Human Resources department directly.
                            </p>
                            <a class="btn btn-outline-secondary w-100" href="<?= e(url('home.php')) ?>"><?= icon('arrow-left') ?> Back to home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include BASE_PATH . '/includes/layout/public_footer.php'; ?>
