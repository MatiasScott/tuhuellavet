<?php
function storage_path(string $p = ''): string
{
    return STORAGE_PATH . ($p !== '' ? '/' . ltrim($p, '/') : '');
}
