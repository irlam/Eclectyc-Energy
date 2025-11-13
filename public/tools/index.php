<?php
/**
 * File: public/tools/index.php
 * Redirect handler for /tools/ directory access
 * 
 * This file ensures that when /tools/ is accessed as a directory,
 * it properly redirects to the application route instead of causing
 * Apache directory listing errors.
 * 
 * Created: 13/11/2025
 */

// Redirect to /tools (without trailing slash)
// This will be handled by the Slim application router
header('Location: /tools', true, 301);
exit;
