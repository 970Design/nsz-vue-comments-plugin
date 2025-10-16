=== 970 Design Comments (Headless) ===
Contributors: 970design
Tags: comments, headless, rest api, vue, astro, recaptcha
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Secure proxy endpoints for headless WordPress comments integration with reCAPTCHA v3 spam protection.

== Description ==

970 Design Headless Comments provides secure REST API endpoints for managing WordPress comments in a headless environment. Perfect for decoupled architectures using Vue.js, Astro.js, React, or any other JavaScript framework.

**Features:**

* Secure API key authentication
* RESTful endpoints for fetching and submitting comments
* **Optional reCAPTCHA v3 spam protection**
* CORS support with configurable allowed origins
* Automatic comment moderation integration
* Email validation
* IP address tracking for spam prevention
* Support for threaded/nested comments
* Respects all WordPress comment settings
* Built-in spam detection

== Installation ==

**Requirements:**
* WordPress 5.8 or higher
* PHP 7.4 or higher

**Installation Steps:**

1. Upload the plugin files to `/wp-content/plugins/970-design-headless-comments/` directory.

2. Activate the plugin through the 'Plugins' menu in WordPress.

3. Navigate to **Settings > Headless Comments** in your WordPress admin.

4. Copy the automatically generated API key (or generate a new one if needed).

5. Configure your allowed origins (one per line).

6. (Optional) Enable reCAPTCHA v3 and add your site key from [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin).

7. Save your settings.

**Frontend Setup (Vue.js/Astro.js):**

1. Create a Comment component in your frontend project that calls the API endpoints.

2. Example usage:
   ```vue
   <Comment
     :post-id="123"
     endpoint="https://your-wordpress-site.com"
     api-key="your-api-key-here"
   />
   ```

3. Set environment variables for your API key and WordPress endpoint.

== API Endpoints ==

**Get reCAPTCHA Configuration**
```
GET /wp-json/headless-comments/v1/recaptcha/config
```
Headers: `X-API-Key: your-api-key`

Returns:
```json
{
  "enabled": true,
  "site_key": "your-site-key"
}
```

**Get Comments**
```
GET /wp-json/headless-comments/v1/posts/{post_id}/comments
```
Headers: `X-API-Key: your-api-key`

Returns all approved comments for the specified post.

**Submit Comment**
```
POST /wp-json/headless-comments/v1/posts/{post_id}/comments
```
Headers: `X-API-Key: your-api-key`
Content-Type: `application/json`

Body:
```json
{
  "author_name": "John Doe",
  "author_email": "john@example.com",
  "content": "Great post!",
  "parent": 0,
  "recaptcha_token": "recaptcha-token-from-frontend"
}
```

Note: `recaptcha_token` is required only if reCAPTCHA v3 is enabled in plugin settings.

Submits a new comment to the specified post. Comments may require moderation based on WordPress settings.

== Configuration ==

**API Key**
A secure API key is automatically generated on plugin activation. You can regenerate it at any time from the settings page. The API key is required for all API requests.

**Allowed Origins**
Configure which domains can access your API endpoints. Add one origin per line:
```
https://yoursite.com
http://localhost:4321
```

Use `*` to allow all origins (not recommended for production).

**reCAPTCHA v3 (Optional)**
Enable spam protection with Google reCAPTCHA v3:
1. Get your keys from [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Select reCAPTCHA v3
3. Add your site key (public key) in plugin settings
4. Enable the checkbox to activate reCAPTCHA verification

**CORS Settings**
The plugin automatically handles CORS headers based on your allowed origins configuration. Credentials are enabled by default for authenticated requests.

NOTE: CORS must also be configured at your host to allow requests from your frontend domain in addition to this plugin's settings.

== Frequently Asked Questions ==

= Is the API secure? =
Yes, all endpoints require API key authentication via the `X-API-Key` header. Additionally, you can restrict access by domain using CORS settings. Optional reCAPTCHA v3 provides extra spam protection.

= How do I set up reCAPTCHA v3? =
1. Visit [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Register your site and select reCAPTCHA v3
3. Copy the site key
4. Add it to plugin settings under **Settings > Headless Comments**
5. Enable the reCAPTCHA checkbox
6. In your frontend, load the reCAPTCHA script and generate tokens before submitting comments

= Can I use this with React or other frameworks? =
Yes! The API endpoints work with any JavaScript framework including React, Vue, Svelte, Angular, or vanilla JavaScript.

= What happens to comment notifications? =
All WordPress comment notifications configured in **Settings > Discussion** will be sent automatically upon comment submission.

= Do comments require moderation? =
Comments follow your WordPress Discussion Settings. They may be auto-approved or require moderation based on your configuration.

= Can I disable comments on specific posts? =
Yes, this plugin respects per-post comment settings. If comments are disabled on a post in WordPress, the API will return a 403 error.

= Does this work with comment spam protection? =
Yes, the plugin integrates with WordPress's built-in comment spam detection, tracks IP addresses for spam prevention, and optionally supports Google reCAPTCHA v3 for enhanced protection.

= Can I customize the comment form? =
Yes, you have complete control over the comment form styling and structure in your frontend application. The plugin only handles the API layer.

== Changelog ==

= 1.1.1 =
* Added optional reCAPTCHA v3 spam protection
* Added endpoint to fetch reCAPTCHA configuration
* Added settings link on plugins page
* Enhanced security with reCAPTCHA token verification

= 1.0 =
* Initial release
* Secure API key authentication
* GET and POST comment endpoints
* CORS support
* Comment moderation integration
* IP tracking and spam prevention
* Email validation
* Threaded comment support

== Upgrade Notice ==

= 1.1.1 =
Added optional reCAPTCHA v3 spam protection. Existing installations will continue to work without changes.

= 1.0 =
Initial release of the headless comments plugin.
