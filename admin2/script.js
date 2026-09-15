document.addEventListener("DOMContentLoaded", function () {

    const toggle = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("desktopSidebar");

    if (!toggle || !sidebar) return;


    /* =========================================
       SIDEBAR OPEN / CLOSE
    ========================================= */

    toggle.addEventListener("click", function (e) {

        e.preventDefault();
        e.stopPropagation();

        if (window.innerWidth >= 992) {

            document.body.classList.toggle(
                "sidebar-collapsed"
            );

        } else {

            sidebar.classList.toggle(
                "mobile-sidebar-open"
            );

            document.body.classList.toggle(
                "sidebar-mobile-open"
            );

        }

    });


    /* =========================================
       SUBMENU
       MANUAL TOGGLE
    ========================================= */

    const menuItems = sidebar.querySelectorAll(
        '.sidebar-menu > a[data-bs-toggle="collapse"]'
    );


    menuItems.forEach(function (menuItem) {

        menuItem.addEventListener("click", function (e) {

            e.preventDefault();
            e.stopPropagation();


            const targetId =
                menuItem.getAttribute("href");


            if (!targetId) return;


            const submenu =
                document.querySelector(targetId);


            if (!submenu) {

                console.log(
                    "Submenu not found:",
                    targetId
                );

                return;

            }


            /* =================================
               CLOSE OTHER SUBMENUS
            ================================= */

            sidebar.querySelectorAll(
                ".submenu"
            ).forEach(function (otherMenu) {

                if (otherMenu !== submenu) {

                    otherMenu.classList.remove(
                        "submenu-open"
                    );

                    otherMenu.style.display =
                        "none";


                    const otherToggle =
                        sidebar.querySelector(
                            'a[href="#' +
                            otherMenu.id +
                            '"]'
                        );


                    if (otherToggle) {

                        otherToggle.setAttribute(
                            "aria-expanded",
                            "false"
                        );


                        const otherArrow =
                            otherToggle.querySelector(
                                ".submenu-arrow"
                            );


                        if (otherArrow) {

                            otherArrow.classList.remove(
                                "rotate"
                            );

                        }

                    }

                }

            });


            /* =================================
               OPEN / CLOSE CURRENT
            ================================= */

            if (
                submenu.classList.contains(
                    "submenu-open"
                )
            ) {

                submenu.classList.remove(
                    "submenu-open"
                );

                submenu.style.display = "none";

                menuItem.setAttribute(
                    "aria-expanded",
                    "false"
                );


                const arrow =
                    menuItem.querySelector(
                        ".submenu-arrow"
                    );


                if (arrow) {

                    arrow.classList.remove(
                        "rotate"
                    );

                }

            } else {

                submenu.classList.add(
                    "submenu-open"
                );

                submenu.style.display = "block";

                menuItem.setAttribute(
                    "aria-expanded",
                    "true"
                );


                const arrow =
                    menuItem.querySelector(
                        ".submenu-arrow"
                    );


                if (arrow) {

                    arrow.classList.add(
                        "rotate"
                    );

                }

            }

        });

    });


    /* =========================================
       MOBILE OVERLAY
    ========================================= */

    document.addEventListener("click", function (e) {

        if (window.innerWidth >= 992) return;


        if (
            !sidebar.classList.contains(
                "mobile-sidebar-open"
            )
        ) {
            return;
        }


        if (
            !sidebar.contains(e.target) &&
            !toggle.contains(e.target)
        ) {

            sidebar.classList.remove(
                "mobile-sidebar-open"
            );

            document.body.classList.remove(
                "sidebar-mobile-open"
            );

        }

    });


    /* =========================================
       RESIZE
    ========================================= */

    window.addEventListener("resize", function () {

        if (window.innerWidth >= 992) {

            sidebar.classList.remove(
                "mobile-sidebar-open"
            );

            document.body.classList.remove(
                "sidebar-mobile-open"
            );

        }

    });

});