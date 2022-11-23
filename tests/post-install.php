<?php

if (!file_exists('vendor/bin/_phpunit')) {
    rename('vendor/bin/phpunit', 'vendor/bin/_phpunit');
}

copy('phpunit', 'vendor/bin/phpunit');
chmod('vendor/bin/phpunit', 0o755);
