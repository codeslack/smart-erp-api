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

echo "Creating module at $MODULE_DIR..."

# Create directory structure
mkdir -p "$MODULE_DIR/Controllers"
mkdir -p "$MODULE_DIR/Models"
mkdir -p "$MODULE_DIR/Requests"
mkdir -p "$MODULE_DIR/Resources"
mkdir -p "$MODULE_DIR/Routes"
mkdir -p "$MODULE_DIR/Providers"

# 1. Create Model
cat <<EOT > "$MODULE_DIR/Models/$MODULE_NAME.php"
<?php

namespace App\Modules\\$MODULE_NAME\Models;

use Illuminate\Database\Eloquent\Model;

class $MODULE_NAME extends Model
{
    protected \$fillable = [];
}
EOT

# 2. Create Controller
cat <<EOT > "$MODULE_DIR/Controllers/${MODULE_NAME}Controller.php"
<?php

namespace App\Modules\\$MODULE_NAME\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\\$MODULE_NAME\Requests\Store${MODULE_NAME}Request;
use App\Modules\\$MODULE_NAME\Requests\Update${MODULE_NAME}Request;
use App\Modules\\$MODULE_NAME\Resources\\${MODULE_NAME}Resource;
use App\Modules\\$MODULE_NAME\Models\\$MODULE_NAME;

class ${MODULE_NAME}Controller extends Controller
{
    public function index()
    {
        return ${MODULE_NAME}Resource::collection(${MODULE_NAME}::all());
    }

    public function store(Store${MODULE_NAME}Request \$request)
    {
        \$model = ${MODULE_NAME}::create(\$request->validated());
        return new ${MODULE_NAME}Resource(\$model);
    }

    public function show(${MODULE_NAME} \$model)
    {
        return new ${MODULE_NAME}Resource(\$model);
    }

    public function update(Update${MODULE_NAME}Request \$request, ${MODULE_NAME} \$model)
    {
        \$model->update(\$request->validated());
        return new ${MODULE_NAME}Resource(\$model);
    }

    public function destroy(${MODULE_NAME} \$model)
    {
        \$model->delete();
        return response()->noContent();
    }
}
EOT

# 3. Create Store Request
cat <<EOT > "$MODULE_DIR/Requests/Store${MODULE_NAME}Request.php"
<?php

namespace App\Modules\\$MODULE_NAME\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store${MODULE_NAME}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
EOT

# 4. Create Update Request
cat <<EOT > "$MODULE_DIR/Requests/Update${MODULE_NAME}Request.php"
<?php

namespace App\Modules\\$MODULE_NAME\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update${MODULE_NAME}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
EOT

# 5. Create Resource
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

# 6. Create Routes file
cat <<EOT > "$MODULE_DIR/Routes/api.php"
<?php

use Illuminate\Support\Facades\Route;
use App\Modules\\$MODULE_NAME\Controllers\\${MODULE_NAME}Controller;

Route::apiResource('${1,,}', ${MODULE_NAME}Controller::class);
EOT

# 7. Create Service Provider
cat <<EOT > "$MODULE_DIR/Providers/${MODULE_NAME}ServiceProvider.php"
<?php

namespace App\Modules\\$MODULE_NAME\Providers;

use Illuminate\Support\ServiceProvider;

class ${MODULE_NAME}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \$this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
EOT

echo "Module '$MODULE_NAME' generated successfully inside app/Modules/!"
