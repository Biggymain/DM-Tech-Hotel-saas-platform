<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Scopes\TenantBranchScope;
use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model {
    use HasTenantAndBranch;
    protected $table = 'test_table';
}

// 1. Unbound context
echo "Unbound SQL:\n";
echo TestModel::toSql() . "\n\n";

// 2. Bound Context
app()->singleton('current_tenant_id', fn() => 5);
app()->singleton('current_branch_id', fn() => 12);

echo "Bound SQL:\n";
echo TestModel::toSql() . "\n\n";
