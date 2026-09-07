<?php
function storage_path(string $p=''):string{return STORAGE_PATH.($p!==''?'/'.ltrim($p,'/'):'');}

function patient_photo_url(
    ?string $filename
): ?string {
    if (!$filename) {
        return null;
    }

    return url(
        '/media/pacientes/'
        . rawurlencode(
            $filename
        )
    );
}