<?php

namespace App\Modules\Category\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Category\Models\Category;

use App\Modules\Category\Services\CategoryService;

use App\Modules\Category\Resources\CategoryResource;

use App\Modules\Category\Requests\StoreCategoryRequest;
use App\Modules\Category\Requests\UpdateCategoryRequest;

class CategoryController extends ApiController
{
    public function __construct(
        protected CategoryService $service
    ) {}

    public function index()
    {
        $categories = $this->service
            ->paginate();

        return $this->success(
            CategoryResource::collection(
                $categories
            ),
            'Categories retrieved successfully.'
        );
    }

    public function store(
        StoreCategoryRequest $request
    )
    {
        $category = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new CategoryResource(
                $category
            ),
            'Category created successfully.',
            201
        );
    }

    public function show(
        Category $category
    )
    {
        return $this->success(
            new CategoryResource(
                $category
            ),
            'Category retrieved successfully.'
        );
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    )
    {

        logger()->info(
            'CategoryController update fired',
            [
                'category_id' => $category->id,
                'request_data' => $request->validated(),
            ]
        );
        
        $category = $this->service->update(
            $category,
            $request->validated()
        );

        return $this->success(
            new CategoryResource(
                $category
            ),
            'Category updated successfully.'
        );
    }

    public function destroy(
        Category $category
    )
    {
        $this->service->delete(
            $category
        );

        return $this->success(
            null,
            'Category deleted successfully.'
        );
    }
}