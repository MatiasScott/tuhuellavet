<?php
use App\Core\Session;

function auth_user(): ?array { return Session::get('auth_user'); }
function auth_id(): ?int { return auth_user()['id'] ?? null; }
function active_environment_id(): ?int { return Session::get('active_environment_id'); }
function active_environment(): ?array { return Session::get('active_environment'); }
function is_authenticated(): bool { return auth_id() !== null; }
function csrf_token(): string { return Session::csrfToken(); }
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}
