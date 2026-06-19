#!/bin/bash

# Check if a module name was provided
if [ -z "$1" ]; then
    echo "Error: Please provide a module name."
    echo "Usage: ./make-module.sh ModuleName"
    exit 1
fi

# Capitalize the first letter of the module name
MODULE_NAME="$(tr '[:lower:]' '[:upper:]' <<< "${1:0:1}")${1:1}"
MODULE_DIR="app/Modules/$MODULE_NAME"

# Check if module already exists
if [ -d "$MODULE_DIR" ]; then
    echo "Error: Module '$MODULE_NAME' already exists at $MODULE_DIR."
    exit 1
fi

echo "🏗️  Scaffolding Domain Module: $MODULE_NAME"
echo "📂 Path: $MODULE_DIR"

# Generate URL slugs: handles pluralization and kebab-casing (e.g., PurchaseOrder -> purchase-orders)
RAW_SLUG=$(echo "$MODULE_NAME" | sed 's/\([A-Z]\)/-\1/g' | sed 's/^-//' | tr '[:upper:]' '[:lower:]')
PLURAL_SLUG="${RAW_SLUG}s"
SINGULAR_VAR=$(echo "${MODULE_NAME:0:1}" | tr '[:upper:]' '[:lower:]')${MODULE_NAME:1}

# Create module subdirectories
mkdir -p "$MODULE_DIR/Controllers"
mkdir -p "$MODULE_DIR/Models"
mkdir -p "$MODULE_DIR/Enums"
mkdir -p "$MODULE_DIR/Repositories/Contracts"
mkdir -p "$MODULE_DIR/Services"
mkdir -p "$MODULE_DIR/Requests"
mkdir -p "$MODULE_DIR/Resources"
mkdir -p "$MODULE_DIR/Routes"
mkdir -p "$MODULE_DIR/Providers"

# 1. Create Model
cat <<EOT > "$MODULE_DIR/Models/$MODULE_NAME.php"
<?php

namespace App\Modules\\$MODULE_NAME\Models;

use App\Core\Tenant\Models\TenantModel;

class $MODULE_NAME extends TenantModel
{
    protected \$fillable = [];
}
EOT

# 2. Create Enums
cat <<EOT > "$MODULE_DIR/Enums/${MODULE_NAME}Status.php"
<?php

namespace App\Modules\\$MODULE_NAME\Enums;

enum ${MODULE_NAME}Status: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
}
EOT

# 3. Create Repository Interface Contract
cat <<EOT > "$MODULE_DIR/Repositories/Contracts/${MODULE_NAME}RepositoryInterface.php"
<?php

namespace App\Modules\\$MODULE_NAME\Repositories\Contracts;

use App\Modules\\$MODULE_NAME\Models\\$MODULE_NAME;
use App\Modules\\$MODULE_NAME\Enums\\${MODULE_NAME}Status;
use Illuminate\Database\Eloquent\Collection;

interface ${MODULE_NAME}RepositoryInterface
{
    public function all(): Collection;
    public function create(array \$data): $MODULE_NAME;
    public function update($MODULE_NAME \$$SINGULAR_VAR, array \$data): $MODULE_NAME;
    public function delete($MODULE_NAME \$$SINGULAR_VAR): bool;
    public function updateStatus($MODULE_NAME \$$SINGULAR_VAR, ${MODULE_NAME}Status \$status): $MODULE_NAME;
}
EOT

# 4. Create Concrete Repository Implementation
cat <<EOT > "$MODULE_DIR/Repositories/${MODULE_NAME}Repository.php"
<?php

namespace App\Modules\\$MODULE_NAME\Repositories;

use App\Modules\\$MODULE_NAME\Models\\$MODULE_NAME;
use App\Modules\\$MODULE_NAME\Enums\\${MODULE_NAME}Status;
use App\Modules\\$MODULE_NAME\Repositories\Contracts\\${MODULE_NAME}RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ${MODULE_NAME}Repository implements ${MODULE_NAME}RepositoryInterface
{
    public function all(): Collection
    {
        return ${MODULE_NAME}::all();
    }

    public function create(array \$data): $MODULE_NAME
    {
        return ${MODULE_NAME}::create(\$data);
    }

    public function update($MODULE_NAME \$$SINGULAR_VAR, array \$data): $MODULE_NAME
    {
        \$$SINGULAR_VAR->update(\$data);
        return \$$SINGULAR_VAR;
    }

    public function delete($MODULE_NAME \$$SINGULAR_VAR): bool
    {
        return \$$SINGULAR_VAR->delete();
    }

    public function updateStatus($MODULE_NAME \$$SINGULAR_VAR, ${MODULE_NAME}Status \$status): $MODULE_NAME
    {
        \$$SINGULAR_VAR->update([
            'status' => \$status->value
        ]);
        return \$$SINGULAR_VAR;
    }
}
EOT

# 5. Create Domain Orchestration Service
cat <<EOT > "$MODULE_DIR/Services/${MODULE_NAME}Service.php"
<?php

namespace App\Modules\\$MODULE_NAME\Services;

use App\Modules\\$MODULE_NAME\Models\\$MODULE_NAME;
use App\Modules\\$MODULE_NAME\Enums\\${MODULE_NAME}Status;
use App\Modules\\$MODULE_NAME\Repositories\Contracts\\${MODULE_NAME}RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ${MODULE_NAME}Service
{
    public function __construct(
        protected ${MODULE_NAME}RepositoryInterface \$repository
    ) {}

    public function getAll(): Collection
    {
        return \$this->repository->all();
    }

    public function create(array \$data): $MODULE_NAME
    {
        return \$this->repository->create(\$data);
    }

    public function update($MODULE_NAME \$$SINGULAR_VAR, array \$data): $MODULE_NAME
    {
        return \$this->repository->update(\$$SINGULAR_VAR, \$data);
    }

    public function delete($MODULE_NAME \$$SINGULAR_VAR): bool
    {
        return \$this->repository->delete(\$$SINGULAR_VAR);
    }

    public function approve($MODULE_NAME \$$SINGULAR_VAR): $MODULE_NAME
    {
        return \$this->repository->updateStatus(\$$SINGULAR_VAR, ${MODULE_NAME}Status::APPROVED);
    }
}
EOT

# 6. Create Controller
cat <<EOT > "$MODULE_DIR/Controllers/${MODULE_NAME}Controller.php"
<?php

namespace App\Modules\\$MODULE_NAME\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\\$MODULE_NAME\Requests\Store${MODULE_NAME}Request;
use App\Modules\\$MODULE_NAME\Requests\Update${MODULE_NAME}Request;
use App\Modules\\$MODULE_NAME\Resources\\${MODULE_NAME}Resource;
use App\Modules\\$MODULE_NAME\Models\\$MODULE_NAME;
use App\Modules\\$MODULE_NAME\Services\\${MODULE_NAME}Service;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ${MODULE_NAME}Controller extends Controller
{
    public function __construct(
        protected ${MODULE_NAME}Service \$service
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ${MODULE_NAME}Resource::collection(\$this->service->getAll());
    }

    public function store(Store${MODULE_NAME}Request \$request): ${MODULE_NAME}Resource
    {
        \$model = \$this->service->create(\$request->validated());
        return new ${MODULE_NAME}Resource(\$model);
    }

    public function show($MODULE_NAME \$$SINGULAR_VAR): ${MODULE_NAME}Resource
    {
        return new ${MODULE_NAME}Resource(\$$SINGULAR_VAR);
    }

    public function update(Update${MODULE_NAME}Request \$request, $MODULE_NAME \$$SINGULAR_VAR): ${MODULE_NAME}Resource
    {
        \$updatedModel = \$this->service->update(\$$SINGULAR_VAR, \$request->validated());
        return new ${MODULE_NAME}Resource(\$updatedModel);
    }

    public function destroy($MODULE_NAME \$$SINGULAR_VAR): Response
    {
        \$this->service->delete(\$$SINGULAR_VAR);
        return response()->noContent();
    }

    public function approve($MODULE_NAME \$$SINGULAR_VAR): ${MODULE_NAME}Resource
    {
        \$approvedModel = \$this->service->approve(\$$SINGULAR_VAR);
        return new ${MODULE_NAME}Resource(\$approvedModel);
    }
}
EOT

# 7. Create Requests
cat <<EOT > "$MODULE_DIR/Requests/Store${MODULE_NAME}Request.php"
<?php

namespace App\Modules\\$MODULE_NAME\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store${MODULE_NAME}Request extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
EOT

cat <<EOT > "$MODULE_DIR/Requests/Update${MODULE_NAME}Request.php"
<?php

namespace App\Modules\\$MODULE_NAME\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update${MODULE_NAME}Request extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
EOT

# 8. Create Resource JSON Transformer
cat <<EOT > "$MODULE_DIR/Resources/${MODULE_NAME}Resource.php"
<?php

namespace App\Modules\\$MODULE_NAME\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ${MODULE_NAME}Resource extends JsonResource
{
    public function toArray(Request \$request): array
    {
        return parent::toArray(\$request);
    }
}
EOT

# 9. Create API Routes File (with named custom endpoint)
cat <<EOT > "$MODULE_DIR/Routes/api.php"
<?php

use Illuminate\Support\Facades\Route;
use App\Modules\\$MODULE_NAME\Controllers\\${MODULE_NAME}Controller;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        '${PLURAL_SLUG}/{${SINGULAR_VAR}}/approve',
        [${MODULE_NAME}Controller::class, 'approve']
    )->name('${PLURAL_SLUG}.approve');

    Route::apiResource(
        '${PLURAL_SLUG}',
        ${MODULE_NAME}Controller::class
    );

});
EOT

# 10. Create Module Service Provider
cat <<EOT > "$MODULE_DIR/Providers/${MODULE_NAME}ServiceProvider.php"
<?php

namespace App\Modules\\$MODULE_NAME\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\\$MODULE_NAME\Repositories\Contracts\\${MODULE_NAME}RepositoryInterface;
use App\Modules\\$MODULE_NAME\Repositories\\${MODULE_NAME}Repository;

class ${MODULE_NAME}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \$this->app->bind(${MODULE_NAME}RepositoryInterface::class, ${MODULE_NAME}Repository::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(
                __DIR__ . '/../Routes/api.php'
            );
    }
}
EOT

echo "✅ Layered Module '$MODULE_NAME' generated successfully with named route endpoints!"
