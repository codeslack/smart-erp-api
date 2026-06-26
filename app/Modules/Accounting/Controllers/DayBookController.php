<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\DayBookService;
use App\Modules\Accounting\Requests\DayBookRequest;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class DayBookController extends Controller
{
    public function __construct(
        protected DayBookService $service
    ) {}

    /**
     * DayBook
     */
    public function index(
        DayBookRequest $request
    ) {

        $data = $this->service
            ->getDayBook(
                $request->validated('from_date'),
                $request->validated('to_date')
            );

        return response()->json([

            'success' => true,
            'message' => 'Day Book fetched successfully',
            'data' => $data,
        ]);
    }
}
