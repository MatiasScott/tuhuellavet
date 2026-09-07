<?php
function can(string $p):bool{$u=auth_user();if(!$u)return false;if(!empty($u['is_super_admin']))return true;return in_array($p,$u['permissions']??[],true);}
