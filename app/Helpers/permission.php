<?php

function can(string $p): bool
{
    $user = auth_user();

    if (!$user) {
        return false;
    }

    $activeRole = $user['active_role'] ?? null;

    // Ningún permiso sin un perfil seleccionado.
    if ($activeRole === null) {
        return false;
    }

    // El perfil activo debe estar autorizado.
    if (!in_array($activeRole, $user['roles'] ?? [], true)) {
        return false;
    }

    // El acceso global solo se aplica cuando se utiliza
    // el perfil SUPER_ADMINISTRADOR.
    if (
        $activeRole === 'SUPER_ADMINISTRADOR'
        && !empty($user['is_super_admin'])
    ) {
        return true;
    }

    // Para los demás perfiles, utilizar exclusivamente
    // los permisos cargados para el rol activo.
    return in_array(
        $p,
        $user['permissions'] ?? [],
        true
    );
}
