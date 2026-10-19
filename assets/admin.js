/* Mad Cow Checkout Notices for WooCommerce — editor JS
 *
 * - Toggles the "Customer Question" block visibility from its master checkbox
 * - Drag-to-reorder on the Options rows
 * - Adds/removes rows in the Options table with a next-index generator that
 *   walks the existing DOM so gaps left by removal don't collide
 *
 * Vanilla JS throughout, with one exception: drag-to-reorder uses the
 * jquery-ui-sortable bundled with WordPress, guarded so its absence only
 * costs the dragging. Nothing on the front end loads jQuery.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        initQuestionToggle();
        initOptionsTable();
        initSortable();
    }

    /* Reindex the option rows so the submitted array matches the visible order.
     *
     * Browsers serialise a form in DOM order, and PHP keeps the order keys
     * arrive in, so a drag would survive a save even without this. Renumbering
     * anyway keeps the markup honest and stops the next person having to know
     * that subtlety to trust the result. */
    function renumber(tbody) {
        var rows = tbody.querySelectorAll('tr.mcwccn-option-row');
        Array.prototype.forEach.call(rows, function (row, index) {
            var fields = row.querySelectorAll('input[name^="mcwccn_question_options["]');
            Array.prototype.forEach.call(fields, function (field) {
                field.name = field.name.replace(
                    /mcwccn_question_options\[\d+\]/,
                    'mcwccn_question_options[' + index + ']'
                );
            });
        });
    }

    /* Drag-to-reorder, using the jquery-ui-sortable that ships with WordPress.
     *
     * This is the one jQuery call in the plugin and it is guarded: if the
     * dependency is ever dropped the table stays perfectly usable, just without
     * dragging. The helper clones the row with its cell widths pinned, because
     * a table row torn out of its table otherwise collapses while dragging. */
    function initSortable() {
        var table = document.getElementById('mcwccn-options-table');
        if (!table || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.sortable) {
            return;
        }

        var $ = window.jQuery;
        var tbody = table.querySelector('tbody');

        $(tbody).sortable({
            items: '> tr.mcwccn-option-row',
            handle: '.mcwccn-option-handle',
            axis: 'y',
            cursor: 'move',
            placeholder: 'mcwccn-option-placeholder',
            forcePlaceholderSize: true,
            helper: function (event, row) {
                var originals = row.children();
                var helper = row.clone();
                helper.children().each(function (i) {
                    $(this).width(originals.eq(i).width());
                });
                return helper;
            },
            update: function () {
                renumber(tbody);
            }
        });
    }

    function initQuestionToggle() {
        var toggle = document.querySelector('[data-mcwccn-toggle="question"]');
        var targets = document.querySelectorAll('[data-mcwccn-target="question"]');
        if (!toggle || !targets.length) {
            return;
        }

        /* More than one target now: the settings block and the classic-checkout
           warning, which is only worth reading once a question is actually on. */
        function sync() {
            Array.prototype.forEach.call(targets, function (target) {
                target.classList.toggle('is-hidden', !toggle.checked);
            });
        }

        toggle.addEventListener('change', sync);
        sync();
    }

    function initOptionsTable() {
        var addBtn = document.getElementById('mcwccn-option-add');
        var table = document.getElementById('mcwccn-options-table');
        if (!addBtn || !table) {
            return;
        }

        var tbody = table.querySelector('tbody');

        function nextIndex() {
            var rows = tbody.querySelectorAll('tr.mcwccn-option-row');
            var max = -1;
            rows.forEach(function (row) {
                var input = row.querySelector('input[name^="mcwccn_question_options["]');
                if (!input) {
                    return;
                }
                var m = input.name.match(/mcwccn_question_options\[(\d+)\]/);
                if (m && parseInt(m[1], 10) > max) {
                    max = parseInt(m[1], 10);
                }
            });
            return max + 1;
        }

        /* New rows are copied from a row the server rendered, captured before any
           can be removed, so they carry its translated title, placeholder and
           button text instead of hardcoded English. */
        var firstRow = tbody.querySelector('tr.mcwccn-option-row');
        var template = firstRow ? firstRow.cloneNode(true) : null;

        addBtn.addEventListener('click', function () {
            if (!template) {
                return;
            }
            var i = nextIndex();
            var tr = template.cloneNode(true);
            var fields = tr.querySelectorAll('input[name^="mcwccn_question_options["]');
            Array.prototype.forEach.call(fields, function (field) {
                field.value = '';
                field.removeAttribute('value');
                field.name = field.name.replace(
                    /mcwccn_question_options\[\d+\]/,
                    'mcwccn_question_options[' + i + ']'
                );
            });
            tbody.appendChild(tr);
        });

        table.addEventListener('click', function (e) {
            if (!e.target.classList.contains('mcwccn-option-remove')) {
                return;
            }
            var row = e.target.closest('tr.mcwccn-option-row');
            if (!row) {
                return;
            }
            // Keep at least one row so the admin can always add data back.
            if (tbody.querySelectorAll('tr.mcwccn-option-row').length <= 1) {
                row.querySelectorAll('input').forEach(function (input) {
                    input.value = '';
                });
                return;
            }
            row.remove();
        });
    }
})();
