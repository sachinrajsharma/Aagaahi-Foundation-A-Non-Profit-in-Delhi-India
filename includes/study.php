<?php
/** Study page (Class 1-12 lessons from the Vidaara content API). No server code needed: it is a small JS app. */
function study_api(): string
{
    global $ngo;
    return $ngo['study']['api_base'] ?? 'https://vidaara.org/api/v1';
}
