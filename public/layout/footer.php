<?php

?>
        </div>
    </main>

    <footer class="sams-footer mt-auto py-4">
        <div class="container-fluid px-4">
            <div class="row align-items-center gy-3">
                <div class="col-md-5 text-center text-md-start">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                        <i class="fa-solid fa-graduation-cap text-warning fs-5"></i>
                        <span class="fw-bold text-white"><?= e(APP_UNIVERSITY) ?></span>
                    </div>
                    <div class="text-white-50 small">
                        <?= e(APP_FACULTY) ?> &bull; <?= e(APP_DEPT) ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.725rem;">
                        Mihintale, Anuradhapura 50000, Sri Lanka &bull; Official Attendance Management System (EIS)
                    </div>
                </div>

                <div class="col-md-4 text-center">
                    <div class="d-flex justify-content-center gap-3 small text-white-50">
                        <a href="http://localhost:8000" class="text-warning"><i class="fa-solid fa-laptop-code me-1"></i> localhost:8000</a>
                        <span>&bull;</span>
                        <a href="https://www.rjt.ac.lk" target="_blank" rel="noopener"><i class="fa-solid fa-globe me-1 text-primary"></i> RUSL Web</a>
                        <span>&bull;</span>
                        <a href="mailto:helpdesk@as.rjt.ac.lk"><i class="fa-solid fa-envelope me-1 text-warning"></i> IT Support</a>
                        <span>&bull;</span>
                        <a href="verify_email.php"><i class="fa-solid fa-shield-halved me-1 text-info"></i> Security</a>
                    </div>
                </div>

                <div class="col-md-3 text-center text-md-end">
                    <span class="badge bg-dark border border-secondary text-white-50 px-3 py-1 font-mono">
                        <i class="fa-solid fa-circle text-success me-1" style="font-size: 0.5rem;"></i> System Online (v2.4.0)
                    </span>
                    <div class="text-muted mt-1" style="font-size: 0.7rem;">
                        &copy; <?= date('Y') ?> Department of Computing. All rights reserved.
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <?php if (isLoggedIn()): ?>

    <div class="modal fade" id="inactivityWarningModal" tabindex="-1" aria-labelledby="inactivityWarningModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-warning-subtle text-warning rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-dark" id="inactivityWarningModalLabel">Session Inactivity Warning</h5>
                    </div>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted mb-2">
                        You have been inactive for nearly 5 minutes. For your university account security, your session will automatically terminate in:
                    </p>
                    <div class="text-center my-3">
                        <span class="badge bg-danger fs-2 font-mono px-4 py-2" id="inactivityCountdown">30s</span>
                    </div>
                    <p class="text-muted small mb-0 text-center">
                        Move your cursor, type on your keyboard, or click below to keep your session active.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                    <a href="logout.php?reason=timeout" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Sign Out Now
                    </a>
                    <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" id="keepLoggedInBtn" data-bs-dismiss="modal">
                        <i class="fa-solid fa-user-check me-1"></i> Keep Me Signed In
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

    <script src="assets/js/app.js"></script>
</body>
</html>
