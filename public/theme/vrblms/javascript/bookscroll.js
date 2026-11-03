/*
 * theme_vrblms — Book activity: all chapters on one scrolling page.
 *
 * mod_book's view.php renders exactly one chapter per request (no renderer
 * to override, and core must not be edited). This progressive enhancement
 * runs only on the book view page (body#page-mod-book-view):
 *  - reads the chapter list from the book's own table of contents block
 *    (so it shows exactly the chapters Moodle already shows this user —
 *    hidden chapters never appear there for employees);
 *  - fetches each other chapter's normal view.php page (same session, same
 *    permission checks, same rewritten image URLs, and each fetch logs the
 *    usual "chapter viewed" event) and inserts its #mod_book-chapter box in
 *    TOC order around the current one;
 *  - hides the prev/next chapter arrows and turns TOC links into in-page
 *    jumps, then scrolls to the chapter that was asked for.
 * If anything fails, the page simply stays as Moodle rendered it (one
 * chapter + arrows). Plain footer script (theme config.php) — no AMD build.
 * Styling: style/custom.css section 25.
 */
(function () {
    "use strict";

    if (document.body.id !== "page-mod-book-view") {
        return;
    }

    var current = document.getElementById("mod_book-chapter");
    var toc = document.querySelector(".block_book_toc .book_toc");
    if (!current || !toc || !window.fetch || !window.DOMParser) {
        return;
    }

    var currentId = (new URL(window.location.href)).searchParams.get("chapterid");

    // TOC entries in document order: <a href="...chapterid=N"> for other
    // chapters, a bare <strong> for the current one.
    var entries = [];
    toc.querySelectorAll("li").forEach(function (li) {
        var link = li.querySelector(":scope > a");
        var strong = li.querySelector(":scope > strong");
        if (link) {
            var id = (new URL(link.href, window.location.href)).searchParams.get("chapterid");
            if (id) {
                entries.push({id: id, href: link.href, link: link});
            }
        } else if (strong) {
            entries.push({id: "current", strong: strong});
        }
    });
    if (entries.length < 2) {
        return;
    }

    current.classList.add("vrb-book-chapter");
    current.setAttribute("data-vrb-chapter", "current");

    var fetches = entries.map(function (entry) {
        if (entry.id === "current") {
            return Promise.resolve(current);
        }
        return fetch(entry.href, {credentials: "same-origin"})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("HTTP " + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, "text/html");
                var box = doc.getElementById("mod_book-chapter");
                if (!box) {
                    throw new Error("no chapter box");
                }
                box.removeAttribute("id");
                box.classList.add("vrb-book-chapter");
                box.id = "vrb-book-chapter-" + entry.id;
                return document.importNode(box, true);
            });
    });

    Promise.all(fetches).then(function (boxes) {
        // Rebuild the chapter list in TOC order around the current box.
        var parent = current.parentNode;
        var anchor = document.createComment("vrb-book-chapters");
        parent.insertBefore(anchor, current);
        var ref = anchor;
        boxes.forEach(function (box) {
            parent.insertBefore(box, ref.nextSibling);
            ref = box;
        });
        if (!current.id) {
            current.id = "mod_book-chapter";
        }

        document.body.classList.add("vrb-book-allchapters");

        // TOC: every entry becomes an in-page jump.
        entries.forEach(function (entry, i) {
            var target = boxes[i];
            var el = entry.link || entry.strong;
            el.addEventListener("click", function (e) {
                e.preventDefault();
                target.scrollIntoView({behavior: "smooth", block: "start"});
            });
            if (entry.strong) {
                entry.strong.style.cursor = "pointer";
            }
        });

        // Land on the chapter that was asked for (the current one), unless
        // the book was opened at its start.
        if (currentId && boxes[0] !== current) {
            current.scrollIntoView({block: "start"});
        }
    }).catch(function () {
        // Leave Moodle's normal one-chapter page untouched.
    });
})();
