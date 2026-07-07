<?php

/**
 * Router para el servidor embebido de PHP (solo desarrollo).
 *
 * Uso:  php -S 127.0.0.1:8000 api/router.php
 *
 * En dev, el servidor de Vite sirve el frontend y hace proxy de /api a este
 * servidor PHP, así que enrutamos todo al front controller de la API.
 */

require __DIR__ . '/index.php';
