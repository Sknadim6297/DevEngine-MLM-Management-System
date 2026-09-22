<div class="genealogy-toolbar" role="toolbar" aria-label="Genealogy controls">
    <button type="button" class="btn btn-primary btn-sm" data-tree-action="expand">
        <i class="bi bi-arrows-expand"></i>
        <span>Expand All</span>
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-tree-action="collapse">
        <i class="bi bi-arrows-collapse"></i>
        <span>Collapse All</span>
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-tree-action="zoom-out" aria-label="Zoom out">
        <i class="bi bi-zoom-out"></i>
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-tree-action="zoom-in" aria-label="Zoom in">
        <i class="bi bi-zoom-in"></i>
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-tree-action="fit">
        <i class="bi bi-fullscreen"></i>
        <span>Fit Tree</span>
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-tree-action="reset">
        <i class="bi bi-crosshair"></i>
        <span>Center / Reset View</span>
    </button>
    <span class="genealogy-zoom-label" data-tree-zoom>100%</span>
</div>

<script>
    function initializeGenealogyControls() {
        const treeContainer = document.getElementById('treeContainer');
        const tree = treeContainer?.querySelector('.member-tree');
        if (!tree || tree.dataset.controlsBound === 'true') {
            return;
        }

        tree.dataset.controlsBound = 'true';
        let scale = 1;
        const minScale = 0.6;
        const maxScale = 1.5;
        const step = 0.1;
        const zoomLabel = document.querySelector('[data-tree-zoom]');

        function updateZoom() {
            tree.style.setProperty('--tree-scale', scale.toFixed(2));
            if (zoomLabel) {
                zoomLabel.textContent = Math.round(scale * 100) + '%';
            }
        }

        function setExpanded(node, expanded) {
            const children = node.querySelector(':scope > .tree-children');
            const toggle = node.querySelector(':scope > .tree-member [data-tree-toggle]');
            if (!children || !toggle) {
                return;
            }

            children.hidden = !expanded;
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.innerHTML = expanded
                ? '<i class="bi bi-chevron-down"></i>'
                : '<i class="bi bi-chevron-right"></i>';
        }

        function allNodes() {
            return tree.querySelectorAll('[data-member-node]');
        }

        function expandAll(expanded) {
            allNodes().forEach(function (node) {
                setExpanded(node, expanded);
            });
        }

        tree.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-tree-toggle]');
            if (toggle) {
                event.preventDefault();
                const node = toggle.closest('[data-member-node]');
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                setExpanded(node, !expanded);
            }
        });

        document.querySelectorAll('[data-tree-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                const action = button.dataset.treeAction;
                if (action === 'expand') {
                    expandAll(true);
                } else if (action === 'collapse') {
                    expandAll(false);
                    setExpanded(tree.querySelector(':scope > [data-member-node]'), true);
                } else if (action === 'zoom-in') {
                    scale = Math.min(maxScale, scale + step);
                    updateZoom();
                } else if (action === 'zoom-out') {
                    scale = Math.max(minScale, scale - step);
                    updateZoom();
                } else if (action === 'fit') {
                    scale = Math.min(1, treeContainer.clientWidth / Math.max(tree.scrollWidth, treeContainer.clientWidth));
                    updateZoom();
                    treeContainer.scrollLeft = 0;
                    treeContainer.scrollTop = 0;
                } else if (action === 'reset') {
                    scale = 1;
                    updateZoom();
                    treeContainer.scrollLeft = 0;
                    treeContainer.scrollTop = 0;
                }
            });
        });

        allNodes().forEach(function (node, index) {
            setExpanded(node, index === 0);
        });
        updateZoom();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeGenealogyControls);
    } else {
        initializeGenealogyControls();
    }
</script>
