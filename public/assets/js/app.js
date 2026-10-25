document.addEventListener('DOMContentLoaded', function () {
    const selects = document.querySelectorAll('.att-select');
    selects.forEach(sel => {
        sel.addEventListener('change', function () {
            updateSelectClass(this);
            recalculateRowStats(this.closest('tr'));
        });
        updateSelectClass(sel);
    });

    function updateSelectClass(el) {
        el.classList.remove('val-1', 'val-0', 'val-MC', 'val-Ex', 'val-SRC');
        if (el.value) {
            el.classList.add('val-' + el.value);
        }
    }

    function recalculateRowStats(tr) {
        if (!tr) return;

        let present = 0;
        let absent = 0;
        let mc = 0;
        let ex = 0;
        let src = 0;

        const rowSelects = tr.querySelectorAll('.att-select');
        rowSelects.forEach(s => {
            const val = s.value;
            if (val === '1') {
                present++;
            } else if (val === 'SRC') {
                present++;
                src++;
            } else if (val === '0') {
                absent++;
            } else if (val === 'MC') {
                absent++;
                mc++;
            } else if (val === 'Ex') {
                absent++;
                ex++;
            }
        });

        const table = tr.closest('table');
        const weeksEvaluated = parseInt(table.dataset.weeksEvaluated || '14', 10);
        const threshold = parseFloat(table.dataset.threshold || '80');

        const pct = weeksEvaluated > 0 ? ((present / weeksEvaluated) * 100).toFixed(1) : '0.0';

        const cellPresent = tr.querySelector('.cell-present');
        const cellAbsent = tr.querySelector('.cell-absent');
        const cellPct = tr.querySelector('.cell-pct');
        const cellEligibility = tr.querySelector('.cell-eligibility');

        if (cellPresent) cellPresent.textContent = present;
        if (cellAbsent) cellAbsent.textContent = absent;
        if (cellPct) cellPct.textContent = pct + '%';

        if (cellEligibility) {
            const isEligible = parseFloat(pct) >= threshold;
            const isPractical = table.dataset.isPractical === '1';

            if (isEligible) {
                cellEligibility.innerHTML = '<span class="badge-eligible">' + (isPractical ? 'Eligible (Pending HoD)' : 'Eligible') + '</span>';
            } else {
                cellEligibility.innerHTML = '<span class="badge-ineligible">' + (isPractical ? 'Not Eligible (Pending HoD)' : 'Not Eligible') + '</span>';
            }
        }
    }

    window.markAllForWeek = function(weekNum, statusVal) {
        const selects = document.querySelectorAll('.week-col-' + weekNum);
        selects.forEach(sel => {
            if (!sel.disabled) {
                sel.value = statusVal;
                updateSelectClass(sel);
                recalculateRowStats(sel.closest('tr'));
            }
        });
    };

    const searchInputs = document.querySelectorAll('.table-quick-search');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function () {
            const term = this.value.toLowerCase();
            const targetTable = document.querySelector(this.dataset.target);
            if (!targetTable) return;

            const rows = targetTable.querySelectorAll('tbody tr');
            rows.forEach(r => {
                const text = r.textContent.toLowerCase();
                r.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });

    if (window.SAMS_USER_LOGGED_IN) {
        const timeoutSeconds = window.SAMS_INACTIVITY_LIMIT || 300;
        const warningSeconds = 30;
        const warningThreshold = Math.max(0, timeoutSeconds - warningSeconds);

        let lastActivity = Date.now();
        let warningModalInstance = null;
        let isWarningModalOpen = false;

        const warningModalEl = document.getElementById('inactivityWarningModal');
        const countdownEl = document.getElementById('inactivityCountdown');
        const keepLoggedInBtn = document.getElementById('keepLoggedInBtn');

        if (warningModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            warningModalInstance = new bootstrap.Modal(warningModalEl);
        }

        function resetInactivity() {
            const now = Date.now();
            if (now - lastActivity > 15000) {
                fetch('ping.php', { method: 'POST', cache: 'no-store' }).catch(() => {});
            }
            lastActivity = now;

            if (isWarningModalOpen && warningModalInstance) {
                warningModalInstance.hide();
                isWarningModalOpen = false;
            }
        }

        const activityEvents = ['mousemove', 'mousedown', 'keydown', 'keypress', 'touchstart', 'scroll', 'click'];

        let throttleTimer = null;
        activityEvents.forEach(evt => {
            window.addEventListener(evt, function () {
                if (!throttleTimer) {
                    throttleTimer = setTimeout(function () {
                        resetInactivity();
                        throttleTimer = null;
                    }, 500);
                }
            }, { passive: true });
        });

        if (keepLoggedInBtn) {
            keepLoggedInBtn.addEventListener('click', function () {
                resetInactivity();
                fetch('ping.php', { method: 'POST', cache: 'no-store' }).catch(() => {});
            });
        }

        const inactivityInterval = setInterval(function () {
            const idleSeconds = Math.floor((Date.now() - lastActivity) / 1000);

            if (idleSeconds >= timeoutSeconds) {
                clearInterval(inactivityInterval);
                window.location.href = window.SAMS_LOGOUT_URL || 'logout.php?reason=timeout';
            } else if (idleSeconds >= warningThreshold) {
                const remaining = timeoutSeconds - idleSeconds;
                if (countdownEl) {
                    countdownEl.textContent = remaining + 's';
                }
                if (!isWarningModalOpen && warningModalInstance) {
                    warningModalInstance.show();
                    isWarningModalOpen = true;
                }
            } else {
                if (isWarningModalOpen && warningModalInstance) {
                    warningModalInstance.hide();
                    isWarningModalOpen = false;
                }
            }
        }, 1000);
    }
});
