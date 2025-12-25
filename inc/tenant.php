<?php
declare(strict_types=1);

function tenant_id(): int {
  $u = auth_user();
  if (!$u) {
    http_response_code(401);
    exit('Unauthorized');
  }
  return (int)$u['tenant_id'];
}
