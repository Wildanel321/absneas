<?php

declare(strict_types=1);

fwrite(STDOUT, "APP_KEY=" . bin2hex(random_bytes(32)) . PHP_EOL);
fwrite(STDOUT, "KIOSK_KEY=" . bin2hex(random_bytes(16)) . PHP_EOL);
