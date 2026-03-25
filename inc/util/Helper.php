<?php

function CSHR_send_email($email, $data, $title)
{    
    $content = CSHR_template(CSHR_PLUGIN_PATH . '/templates/emails/email-template.php', $data);
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    return wp_mail($email, $title, $content, $headers);
}

function CSHR_template($file, $args)
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
