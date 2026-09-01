<?php

// Deliberately not a complete application config; the inspector must only
// inspect text and must not execute this file.
return ['default' => getenv('UNTRUSTED_PROJECT_VALUE')];
