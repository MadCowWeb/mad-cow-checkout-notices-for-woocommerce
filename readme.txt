=== Mad Cow Checkout Notices for WooCommerce ===
Contributors: jrobie23
Tags: woocommerce, checkout, notice, checkout field, order meta
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show targeted notices on the WooCommerce checkout, with an optional question whose answer is saved to the order.

== Description ==

Add as many checkout notices as you need, each one an ordinary entry under WooCommerce → Checkout Notices. A notice can be a simple message — an order cutoff, a closure, a delivery delay — or it can ask the customer a question and store their answer on the order.

Each notice is independent. It has its own message, its own schedule, its own targeting rules, and its own question. Nothing is hardcoded and nothing requires a developer to change.

**Targeting**

A notice can be limited to carts containing particular product categories, particular product IDs, or both. Leave both empty and it shows on every non-empty checkout page. Give it a start date, an end date, or neither.

**Appearance**

Each notice carries its own styling, so two notices on the same checkout can look completely different. Heading size, weight and colour; question label size, weight and colour; background colour (or none); border width, style and colour; a thicker left accent bar; corner radius; and padding and margin on all four sides. Defaults give you a plain bordered box; change what you need and leave the rest.

The message itself is written in the full WordPress editor, so colours, alignment, lists, links and images are set as you write rather than through separate fields.

**The optional question**

Turn the question on and the notice renders a dropdown or a set of radio buttons underneath its message, built with WooCommerce's own form-field helper so it inherits your checkout styling.

Each option has two parts:

* **Display Label** — what the customer sees. Leave it blank on a dated option and the date is shown, formatted, for example "Tuesday, December 16". A blank label follows the date if you change it; a label you type is shown as written.
* **Auto-Hide On/After** — an optional date. That option disappears from checkout on and after that date. Leave it blank for options that should always be available. When every option has a date and all of them have passed, the whole notice hides itself.

Drag the handle beside a row to change the order options appear in at checkout.

What gets stored on the order is worked out for you: a dated option saves its date, anything else saves its label. The order screen and emails show the label the customer chose.

You can require an answer. With that on, nothing is pre-selected, the label carries an asterisk, and an order cannot be placed until the customer picks an option — validated server-side through WooCommerce's own checkout validation, so the error appears where shoppers already expect checkout errors.

You choose the label used when the answer is shown on the order edit screen and in order emails.

Answers are validated against the options the notice actually offered before anything is written to the order.

**Classic and block checkout**

Notices appear on both: the classic `[woocommerce_checkout]` shortcode and the newer Checkout block. The question is classic-only, for the reason given in the FAQ.

**Built for WooCommerce**

* Declares High-Performance Order Storage (HPOS) compatibility. Order meta is read and written through the order CRUD API.
* The message field is the full WordPress editor, with both toolbar rows open and the media library available.
* Notices are a private post type visible only to users with `manage_woocommerce`.
* No external requests, no tracking, no bundled fonts or icon libraries. The one icon is an inline SVG.

**Developer notes**

Rows shown on the admin order screen and in order emails pass through a filter:

`apply_filters( 'mcwccn_order_meta_rows', $rows, $order )`

`$rows` is an array of `array( 'label' => string, 'value' => string )`. Return the array modified, reordered, or emptied.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin through Plugins → Add New → Upload Plugin, or install it from the WordPress plugin directory.
3. Activate it.
4. Go to WooCommerce → Checkout Notices and add your first notice.

== Frequently Asked Questions ==

= Does this work with the Checkout block? =

Notices do. They are placed above the Checkout block with the same targeting, scheduling and styling they use on the classic checkout, and no JavaScript is involved.

The optional question is classic checkout only. The Checkout block submits through WooCommerce's Store API rather than a form post, so a field rendered alongside the block would appear, the customer would answer it, and the answer would never reach the order. Rather than lose an answer silently, a notice that asks a question is skipped on the block checkout. Message-only notices work on both.

= Where do the answers end up? =

On the order, in its own meta field. They show on the order edit screen and inside order emails under the Admin/Email Label you set, and are available to anything else that reads order meta.

= What happens to a notice when all of its dates pass? =

It stops rendering. Individual options disappear on and after their own date, and once every dated option has passed the whole notice hides itself. Nothing needs to be switched off by hand.

= Does the Enabled checkbox do the same thing as Publish? =

No, and that is deliberate. Publish controls whether the notice exists; Enabled controls whether it shows at checkout. A published notice with Enabled switched off stays hidden, which lets you keep a seasonal notice fully configured between seasons.

= Does deleting the plugin delete my order data? =

No. Uninstalling removes the notices and their settings. Answers already stored on orders are left alone, because they belong to the order record.

== Screenshots ==

1. Editing a notice: the Notice Appearance box (background, border, accent bar, padding and margin) and the Notice Content box (heading, heading style and the full WordPress editor for the message).
2. The When to Show box: an optional start and end date, plus cart targeting by product category or by product ID.
3. The Customer Question box: field type, required answer, the block-checkout notice, and the drag-to-reorder options table with auto-hide dates.
4. The finished notice on a classic (shortcode) checkout, with its question rendered as radio buttons.
5. The customer's order email, showing the saved answer alongside the order summary.

== Changelog ==

= 1.0.0 =
* Initial release.
