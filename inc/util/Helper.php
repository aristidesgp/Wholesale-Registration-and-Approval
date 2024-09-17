<?php

function wra_send_email($email, $data, $title)
{    
    $content = wra_template(WRA_PLUGIN_PATH . '/templates/emails/email-template.php', $data);
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    return wp_mail($email, $title, $content, $headers);
}

function wra_template($file, $args)
{
    // ensure the file exists
    if (!file_exists($file)) {
        return '';
    }

    // Make values in the associative array easier to access by extracting them
    if (is_array($args)) {
        extract($args);
    }

    // buffer the output (including the file is "output")
    ob_start();
    include $file;
    return ob_get_clean();
}
