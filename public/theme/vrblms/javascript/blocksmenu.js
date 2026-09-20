/*
 * theme_vrblms — hamburger menu for the right-hand block drawer.
 *
 * Progressive enhancement over Moodle's own drawer (theme_boost/drawers):
 *  - injects a hamburger button into the navbar that carries the same
 *    data-toggler="drawers" attributes as Moodle's built-in toggle, so
 *    Moodle's delegated click handler opens/closes the drawer (and keeps
 *    the user preference) — no drawer logic is reimplemented here;
 *  - clicking anywhere outside the open drawer (or pressing Esc) closes it,
 *    by clicking the drawer's own close button;
 *  - starts closed: a drawer server-rendered open (saved user preference)
 *    is closed once on load, which also persists "closed".
 * Plain footer script (declared in the theme config.php) — no AMD build.
 * Styling: style/custom.css section 22.
 */
(function () {
    "use strict";

    var DRAWER_ID = "theme_boost-drawers-blocks";
    var drawer = document.getElementById(DRAWER_ID);
    var userNav = document.getElementById("usernavigation");
    if (!drawer || !userNav) {
        return;
    }

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "vrb-blocks-hamburger";
    btn.setAttribute("data-toggler", "drawers");
    btn.setAttribute("data-action", "toggle");
    btn.setAttribute("data-target", DRAWER_ID);
    btn.setAttribute("aria-label", "Menu");
    btn.setAttribute("aria-controls", DRAWER_ID);
    btn.innerHTML = "<span></span><span></span><span></span>";
    userNav.appendChild(btn);

    function isOpen() {
        return drawer.classList.contains("show");
    }

    function closeDrawer() {
        var closeBtn = drawer.querySelector('[data-toggler="drawers"][data-action="closedrawer"]');
        if (closeBtn) {
            closeBtn.click();
        }
    }

    document.addEventListener("click", function (e) {
        if (!isOpen()) {
            return;
        }
        // Clicks inside the drawer, or on any drawer toggle (incl. the
        // hamburger, which Moodle handles itself), are not "outside".
        if (drawer.contains(e.target) || e.target.closest('[data-toggler="drawers"]')) {
            return;
        }
        closeDrawer();
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && isOpen()) {
            closeDrawer();
        }
    });

    // Start closed. Moodle's drawer module initialises asynchronously and
    // removes the "not-initialized" class from the drawer once its click
    // handlers are live — closing before that would be a silent no-op, so
    // poll for it (max ~10s) instead of guessing a delay.
    var tries = 0;
    (function closeWhenReady() {
        if (!isOpen() || drawer.dataset.forceopen === "1") {
            return;
        }
        if (drawer.classList.contains("not-initialized")) {
            if (tries++ < 100) {
                setTimeout(closeWhenReady, 100);
            }
            return;
        }
        closeDrawer();
    })();
})();
