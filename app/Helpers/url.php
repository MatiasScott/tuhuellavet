<?php
function url(string $p=''):string{$b=rtrim($_ENV['APP_URL']??'','/');return $b.($p!==''?'/'.ltrim($p,'/'):'');}function asset(string $p):string{return url('assets/'.ltrim($p,'/'));}
