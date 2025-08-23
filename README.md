# Personal Inventory Tracker

This WordPress plugin provides a simple inventory tracker with secure form submissions and REST endpoints.

It demonstrates:

- Nonce validation using `check_admin_referer` and `wp_verify_nonce`.
- Capability checks (`manage_options` for admin operations, `edit_posts` for REST requests).
- Sanitization of input and escaping of output.
- Preventing direct access to PHP files with `defined( 'ABSPATH' )`.
