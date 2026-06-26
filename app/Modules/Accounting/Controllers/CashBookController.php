<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\CashBookService;
use App\Modules\Accounting\Requests\CashBookRequest;
use Dedoc\Scramble\Attributes\Group;

#[Group('Accounting - Reports')]
class CashBookController extends Controller
{
    public function __construct(
        protected CashBookService $service
    ) {}

    /**
     * CashBook
     */
    public function index(
        CashBookRequest $request
    ) {

        $data = $this->service
            ->getCashBook(
                $request->from_date,
                $request->to_date
            );

        return response()->json([

            'success' => true,
            'message' => 'Cash Book fetched successfully',
            'data'  => $data,
        ]);
    }
}