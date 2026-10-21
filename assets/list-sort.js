/* Mad Cow Checkout Notices for WooCommerce — list-table drag-to-reorder
 *
 * Adds a handle to the Order column of the notice list. Drag it with a mouse,
 * finger or pen, or focus it and use the up/down arrow keys. The new order is
 * saved over admin-ajax.php as soon as the row is dropped; if the save fails the
 * list is put back and a notice says so.
 *
 * Vanilla JS, no jQuery. Only loaded while the list is in checkout order.
 */

(function () {
    'use strict';

    var cfg = window.mcwccnListSort;
    if (!cfg) {
        return;
    }

    var ROW_ID = /^post-\d+$/;
    var list = null;
    var dragRow = null;
    var dragStartOrder = [];
    var confirmed = [];
    var saving = false;
    var queued = false;
    var keyTimer = null;

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        list = document.getElementById('the-list');
        if (!list) {
            return;
        }

        addHandles();
        confirmed = currentOrder();

        // Quick Edit swaps a row's markup after saving; put its handle back.
        new MutationObserver(addHandles).observe(list, { childList: true });

        list.addEventListener('pointerdown', onPointerDown);
        list.addEventListener('keydown', onKeyDown);
    }

    function rows() {
        return Array.prototype.filter.call(list.children, function (el) {
            return el.tagName === 'TR' && ROW_ID.test(el.id);
        });
    }

    function currentOrder() {
        return rows().map(function (row) {
            return row.id.replace('post-', '');
        });
    }

    function addHandles() {
        rows().forEach(function (row) {
            var cell = row.querySelector('td.column-mcwccn_menu_order');
            if (!cell || cell.querySelector('.mcwccn-drag-handle')) {
                return;
            }

            var handle = document.createElement('button');
            handle.type = 'button';
            handle.className = 'mcwccn-drag-handle';
            handle.setAttribute('aria-label', cfg.i18n.handleLabel);
            handle.title = cfg.i18n.handleTitle;

            var icon = document.createElement('span');
            icon.className = 'dashicons dashicons-menu';
            icon.setAttribute('aria-hidden', 'true');
            handle.appendChild(icon);

            cell.insertBefore(handle, cell.firstChild);
        });
    }

    /* ── Pointer dragging ─────────────────────────────────────────────── */

    function onPointerDown(e) {
        var handle = e.target.closest('.mcwccn-drag-handle');
        if (!handle || saving) {
            return;
        }
        if (e.pointerType === 'mouse' && e.button !== 0) {
            return;
        }

        e.preventDefault();

        // Touch pointers are implicitly captured by the handle; release so move
        // events follow the finger over the other rows.
        if (handle.hasPointerCapture && handle.hasPointerCapture(e.pointerId)) {
            handle.releasePointerCapture(e.pointerId);
        }

        dragRow = handle.closest('tr');
        dragStartOrder = currentOrder();
        dragRow.classList.add('mcwccn-dragging');
        document.body.classList.add('mcwccn-is-dragging');

        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
        document.addEventListener('pointercancel', onPointerCancel);
    }

    function onPointerMove(e) {
        if (!dragRow) {
            return;
        }

        var el = document.elementFromPoint(e.clientX, e.clientY);
        var over = el ? el.closest('tr') : null;
        if (!over || over === dragRow || over.parentNode !== list || !ROW_ID.test(over.id)) {
            return;
        }

        // Row under the pointer sits above the dragged row: go before it.
        // Otherwise go after it. Swapping on contact is stable because the
        // dragged row is then under the pointer and ignored on the next event.
        if (over.compareDocumentPosition(dragRow) & Node.DOCUMENT_POSITION_FOLLOWING) {
            list.insertBefore(dragRow, over);
        } else {
            list.insertBefore(dragRow, over.nextSibling);
        }
    }

    function endDrag() {
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        document.removeEventListener('pointercancel', onPointerCancel);
        document.body.classList.remove('mcwccn-is-dragging');
        if (dragRow) {
            dragRow.classList.remove('mcwccn-dragging');
        }
        dragRow = null;
    }

    function onPointerUp() {
        endDrag();
        save();
    }

    function onPointerCancel() {
        endDrag();
        restore(dragStartOrder);
    }

    /* ── Keyboard reordering ──────────────────────────────────────────── */

    function onKeyDown(e) {
        var handle = e.target.closest('.mcwccn-drag-handle');
        if (!handle || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) {
            return;
        }

        e.preventDefault();
        if (saving) {
            return;
        }

        var row = handle.closest('tr');
        var sibling = e.key === 'ArrowUp' ? previousRow(row) : nextRow(row);
        if (!sibling) {
            return;
        }

        if (e.key === 'ArrowUp') {
            list.insertBefore(row, sibling);
        } else {
            list.insertBefore(row, sibling.nextSibling);
        }

        // Moving a node drops focus; hand it back to the same handle.
        handle.focus();

        var all = rows();
        speak(cfg.i18n.moved
            .replace('%1$s', all.indexOf(row) + 1)
            .replace('%2$s', all.length));

        window.clearTimeout(keyTimer);
        keyTimer = window.setTimeout(save, 600);
    }

    function previousRow(row) {
        var el = row.previousElementSibling;
        while (el && !ROW_ID.test(el.id)) {
            el = el.previousElementSibling;
        }
        return el;
    }

    function nextRow(row) {
        var el = row.nextElementSibling;
        while (el && !ROW_ID.test(el.id)) {
            el = el.nextElementSibling;
        }
        return el;
    }

    /* ── Saving ───────────────────────────────────────────────────────── */

    function save() {
        window.clearTimeout(keyTimer);

        if (saving) {
            queued = true;
            return;
        }

        var order = currentOrder();
        if (order.join(',') === confirmed.join(',')) {
            return;
        }

        saving = true;
        list.classList.add('mcwccn-saving');
        clearNotice();

        var body = new FormData();
        body.append('action', cfg.action);
        body.append('nonce', cfg.nonce);
        order.forEach(function (id) {
            body.append('ids[]', id);
        });

        fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result || !result.success) {
                    throw new Error('save failed');
                }
                confirmed = order;
                updateNumbers(result.data.orders);
                speak(cfg.i18n.saved);
            })
            .catch(function () {
                queued = false;
                restore(confirmed);
                showError(cfg.i18n.failed);
            })
            .then(function () {
                saving = false;
                list.classList.remove('mcwccn-saving');
                if (queued) {
                    queued = false;
                    save();
                }
            });
    }

    function restore(order) {
        order.forEach(function (id) {
            var row = document.getElementById('post-' + id);
            if (row) {
                list.appendChild(row);
            }
        });
    }

    function updateNumbers(orders) {
        if (!orders) {
            return;
        }
        rows().forEach(function (row) {
            var id = row.id.replace('post-', '');
            var cell = row.querySelector('td.column-mcwccn_menu_order');
            if (!cell || typeof orders[id] === 'undefined') {
                return;
            }

            var text = null;
            Array.prototype.forEach.call(cell.childNodes, function (node) {
                if (node.nodeType === 3) {
                    text = node;
                }
            });

            if (text) {
                text.nodeValue = String(orders[id]);
            } else {
                cell.appendChild(document.createTextNode(String(orders[id])));
            }
        });
    }

    /* ── Feedback ─────────────────────────────────────────────────────── */

    function speak(message, politeness) {
        if (window.wp && window.wp.a11y && window.wp.a11y.speak) {
            window.wp.a11y.speak(message, politeness || 'polite');
        }
    }

    function clearNotice() {
        var old = document.querySelectorAll('.mcwccn-sort-notice');
        Array.prototype.forEach.call(old, function (el) {
            el.parentNode.removeChild(el);
        });
    }

    function showError(message) {
        var anchor = document.querySelector('.wp-header-end');
        if (!anchor) {
            return;
        }

        var box = document.createElement('div');
        box.className = 'notice notice-error mcwccn-sort-notice';
        var p = document.createElement('p');
        p.textContent = message;
        box.appendChild(p);

        anchor.parentNode.insertBefore(box, anchor.nextSibling);
        speak(message, 'assertive');
    }
})();
