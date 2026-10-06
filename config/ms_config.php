<?php
/**
 * ms_config.php  —  Microsoft (Azure / Entra ID) sign-in settings.
 * ---------------------------------------------------------------------
 * Two modes:
 *   • DEMO mode (default): the IDs below are blank, so the "Continue with
 *     Microsoft" button signs you into a demo Microsoft-style account
 *     (riya@outlook.com). No Azure account needed — works out of the box.
 *
 *   • REAL mode: to sign in with an ACTUAL Microsoft account, register a
 *     free app and fill in the two values below:
 *       1. Go to https://portal.azure.com  →  "App registrations"  →
 *          "New registration".
 *       2. Redirect URI (type "Web"):
 *          http://localhost/market-odyssey/ms_callback.php
 *       3. Copy the "Application (client) ID" into MS_CLIENT_ID.
 *       4. "Certificates & secrets" → "New client secret" → copy the
 *          secret VALUE into MS_CLIENT_SECRET.
 *     Once both are filled in, the button uses the real Microsoft login.
 *
 * NOTE: real Microsoft login (OAuth 2.0) goes a little beyond the course
 * syllabus — it is included as an extra. The demo mode keeps everything
 * within the HTML/CSS/PHP/MySQL/JS topics you were asked to use.
 */

define('MS_CLIENT_ID',     '');   // Application (client) ID  — leave blank for demo mode
define('MS_CLIENT_SECRET', '');   // Client secret VALUE      — leave blank for demo mode
define('MS_TENANT',        'common');  // 'common' allows any personal/work Microsoft account
define('MS_REDIRECT',      'http://localhost/market-odyssey/ms_callback.php');

/** True only when real Azure credentials have been supplied. */
function ms_enabled()
{
    return MS_CLIENT_ID !== '' && MS_CLIENT_SECRET !== '';
}
?>
